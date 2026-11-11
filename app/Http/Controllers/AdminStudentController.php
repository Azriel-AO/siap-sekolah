<?php

namespace App\Http\Controllers;

use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminStudentController extends Controller
{
    /**
     * Daftar siswa per kelas (hanya admin).
     */
    public function index(Request $request): View
    {

        $classes = SchoolClass::orderBy('name')->get();

        $kelasDipilih = null;
        if ($slug = $request->query('kelas')) {
            $kelasDipilih = $classes->firstWhere('slug', $slug);
            abort_if($kelasDipilih === null, 404, 'Kelas tidak ditemukan.');
        } else {
            $kelasDipilih = $classes->first(); // kelas pertama (urut nama)
        }

        $students = $kelasDipilih
            ? $kelasDipilih->students()->with(['rfidCard', 'user', 'faceEnrollment'])->withCount('attendances')->orderBy('name')->get()
            : collect();

        return view('admin.students.index', [
            'classes' => $classes,
            'kelasDipilih' => $kelasDipilih,
            'students' => $students,
        ]);
    }

    /**
     * Form tambah siswa (hanya admin).
     */
    public function create(Request $request): View
    {

        return view('admin.students.create', [
            'classes' => SchoolClass::orderBy('name')->get(),
        ]);
    }

    /**
     * Simpan siswa baru + kartu RFID (opsional) + akun login (opsional).
     * Satu transaksi: gagal di tengah → semua batal.
     */
    public function store(Request $request): RedirectResponse
    {

        $validated = $this->validasi($request);

        DB::transaction(function () use ($validated, $request) {
            $student = Student::create([
                'school_class_id' => $validated['school_class_id'],
                'nis' => $validated['nis'],
                'induk' => $validated['induk'] ?? null,
                'name' => $validated['name'],
                'gender' => $validated['gender'],
            ]);

            $this->simpanKartu($student, $validated['card_uid'] ?? null);

            if ($request->boolean('buat_akun')) {
                $this->buatAkun($student);
            }
        });

        return redirect()
            ->route('admin.siswa.index', ['kelas' => SchoolClass::find($validated['school_class_id'])?->slug])
            ->with('status', 'Siswa "'.$validated['name'].'" berhasil ditambahkan.');
    }

    /**
     * Form ubah siswa (hanya admin).
     */
    public function edit(Request $request, Student $student): View
    {

        $student->load(['rfidCard', 'user']);

        return view('admin.students.edit', [
            'student' => $student,
            'classes' => SchoolClass::orderBy('name')->get(),
        ]);
    }

    /**
     * Perbarui siswa + kartu RFID (buat/perbarui) + akun login (opsional, bila belum ada).
     */
    public function update(Request $request, Student $student): RedirectResponse
    {

        $validated = $this->validasi($request, $student);

        DB::transaction(function () use ($validated, $student, $request) {
            $student->update([
                'school_class_id' => $validated['school_class_id'],
                'nis' => $validated['nis'],
                'induk' => $validated['induk'] ?? null,
                'name' => $validated['name'],
                'gender' => $validated['gender'],
            ]);

            $this->simpanKartu($student, $validated['card_uid'] ?? null);

            if ($request->boolean('buat_akun') && ! $student->user_id) {
                $this->buatAkun($student);
            }
        });

        return redirect()
            ->route('admin.siswa.index', ['kelas' => SchoolClass::find($validated['school_class_id'])?->slug])
            ->with('status', 'Data siswa "'.$validated['name'].'" berhasil diperbarui.');
    }

    /**
     * Validasi input form siswa (Bahasa Indonesia).
     */
    private function validasi(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'nis' => ['required', 'string', 'max:20', Rule::unique('students', 'nis')->ignore($student?->id)],
            'induk' => ['nullable', 'string', 'max:20', Rule::unique('students', 'induk')->ignore($student?->id)],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')],
            'card_uid' => ['nullable', 'string', 'max:50', Rule::unique('rfid_cards', 'card_uid')->ignore($student?->rfidCard?->id)],
        ], [
            'name.required' => 'Nama siswa wajib diisi.',
            'nis.required' => 'NIS wajib diisi.',
            'nis.unique' => 'NIS tersebut sudah dipakai siswa lain.',
            'induk.unique' => 'NIS Induk tersebut sudah dipakai siswa lain.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'school_class_id.required' => 'Kelas wajib dipilih.',
            'school_class_id.exists' => 'Kelas yang dipilih tidak ditemukan.',
            'card_uid.unique' => 'UID kartu tersebut sudah dipakai kartu lain.',
        ]);
    }

    /**
     * UID kartu diisi → buat kartu baru atau perbarui kartu siswa yang sudah ada.
     * UID dikosongkan → kartu yang ada tidak diubah.
     */
    private function simpanKartu(Student $student, ?string $uid): void
    {
        $uid = trim((string) $uid);
        if ($uid === '') {
            return;
        }

        $kartu = $student->rfidCard; // kartu pertama milik siswa (pola hasOne)
        if ($kartu) {
            $kartu->update(['card_uid' => $uid, 'is_active' => true]);
        } else {
            RfidCard::create(['student_id' => $student->id, 'card_uid' => $uid, 'is_active' => true]);
        }
    }

    /**
     * Buat akun login siswa: email dari NIS (<nis>@knbi.sch.id),
     * password default "password", role siswa, terhubung via user_id.
     */
    private function buatAkun(Student $student): void
    {
        $email = $student->nis.'@knbi.sch.id';

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'buat_akun' => 'Email '.$email.' sudah dipakai. Siswa belum dibuat — periksa dulu.',
            ]);
        }

        $user = User::create([
            'name' => $student->name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'siswa',
        ]);

        $student->update(['user_id' => $user->id]);
    }

    /**
     * Hapus permanen data siswa (hard delete) beserta seluruh data terkait dalam 1 transaksi DB:
     * - Catatan absensi (& file foto bukti)
     * - Kartu RFID
     * - Data pendaftaran wajah (& folder foto face-photos/{id}/ dari storage privat)
     * - Akun login user terhubung (jika ada)
     * - Data siswa itu sendiri
     */
    public function destroy(Student $student): RedirectResponse
    {
        $namaSiswa = $student->name;
        $classSlug = $student->schoolClass?->slug;

        DB::transaction(function () use ($student) {
            // 1. Hapus catatan absensi miliknya & file foto bukti jika ada
            foreach ($student->attendances as $attendance) {
                if ($attendance->photo_in_path) {
                    Storage::disk('local')->delete($attendance->photo_in_path);
                }
                if ($attendance->photo_out_path) {
                    Storage::disk('local')->delete($attendance->photo_out_path);
                }
            }
            $student->attendances()->delete();

            // 2. Hapus kartu RFID
            $student->rfidCard()->delete();

            // 3. Hapus data wajah dan hapus folder foto face-photos/{id}/ dari disk privat
            if ($student->faceEnrollment) {
                $student->faceEnrollment()->delete();
            }
            Storage::disk('local')->deleteDirectory("face-photos/{$student->id}");

            // 4. Hapus akun login terhubung (bila user_id ada)
            $user = $student->user;
            if ($user) {
                $student->update(['user_id' => null]);
                $user->delete();
            }

            // 5. Hapus data siswa
            $student->delete();
        });

        return redirect()
            ->route('admin.siswa.index', array_filter(['kelas' => $classSlug]))
            ->with('status', 'Data siswa "'.$namaSiswa.'" berhasil dihapus.');
    }
}
