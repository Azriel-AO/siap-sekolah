<?php

namespace App\Http\Controllers;

use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ? $kelasDipilih->students()->with(['rfidCard', 'user', 'faceEnrollment', 'schoolClass'])->withCount('attendances')->orderBy('name')->get()
            : collect();

        Student::preloadCardLabels($students);

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
     * Simpan siswa baru + kartu RFID (opsional) + akun login otomatis.
     * Satu transaksi: gagal di tengah → semua batal.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validasi($request);

        try {
            DB::transaction(function () use ($validated) {
                $student = Student::create([
                    'school_class_id' => $validated['school_class_id'],
                    'nis' => $validated['nis'],
                    'induk' => $validated['induk'] ?? null,
                    'name' => $validated['name'],
                    'gender' => $validated['gender'],
                ]);

                $this->simpanKartu($student, $validated['card_uid'] ?? null);
                $student->syncUserAccount();
            });
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'nis' => $e->getMessage(),
            ]);
        }

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
     * Perbarui siswa + kartu RFID (buat/perbarui) + sinkronisasi akun login otomatis.
     */
    public function update(Request $request, Student $student): RedirectResponse
    {
        $validated = $this->validasi($request, $student);

        try {
            DB::transaction(function () use ($validated, $student) {
                $student->update([
                    'school_class_id' => $validated['school_class_id'],
                    'nis' => $validated['nis'],
                    'induk' => $validated['induk'] ?? null,
                    'name' => $validated['name'],
                    'gender' => $validated['gender'],
                ]);

                $this->simpanKartu($student, $validated['card_uid'] ?? null);
                $student->syncUserAccount();
            });
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'nis' => $e->getMessage(),
            ]);
        }

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
            'name' => ['required', 'string', 'max:100', 'not_regex:/[<>]/'],
            'nis' => [
                'required',
                'regex:/^[A-Za-z0-9]{1,20}$/',
                Rule::unique('students', 'nis')->ignore($student?->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (User::where('email', $value.'@knbi.sch.id')->where('role', '!=', 'siswa')->exists()) {
                        $fail('NIS tersebut menghasilkan email akun yang sudah dipakai oleh akun non-siswa. Gunakan NIS lain.');
                    }
                },
            ],
            'induk' => ['nullable', 'string', 'max:20', 'not_regex:/[<>]/', Rule::unique('students', 'induk')->ignore($student?->id)],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')],
            'card_uid' => ['nullable', 'string', 'max:50', 'not_regex:/[<>]/', Rule::unique('rfid_cards', 'card_uid')->ignore($student?->rfidCard?->id)],
        ], [
            'name.required' => 'Nama siswa wajib diisi.',
            'name.not_regex' => 'Nama siswa tidak boleh mengandung tag HTML atau karakter < dan >.',
            'nis.required' => 'NIS wajib diisi.',
            'nis.regex' => 'NIS hanya boleh berisi huruf dan angka (maksimal 20 karakter).',
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

            // 4. Hapus akun login terhubung (bila user_id atau student_id ada)
            $user = $student->user ?? User::where('student_id', $student->id)->first();
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
