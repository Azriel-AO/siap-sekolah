<?php

namespace App\Http\Controllers;

use App\Models\FaceEnrollment;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FaceTemplateService;
use App\Services\FingerprintService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Terminal Pendaftaran Mandiri Siswa Baru (guest, tanpa login).
 * SELALU terbuka — pengawasan dilakukan guru piket (bukan toggle admin).
 * Semua data siswa (student + kartu + wajah + sidik jari) disimpan
 * dalam SATU transaksi — belum ada akun login.
 */
class PendaftaranController extends Controller
{
    /**
     * Halaman pendaftaran (guest) — selalu aktif.
     */
    public function show(): View
    {
        return view('pendaftaran.index', [
            'classes' => SchoolClass::orderBy('name')->get(),
        ]);
    }

    /**
     * Daftarkan sidik jari siswa ke sensor (server-side ke bridge/mock).
     * Pada Debug Mode, mendukung parameter granular `bypass_fingerprint=1` tanpa menyentuh device lain.
     */
    public function fingerprintEnrolar(Request $request): JsonResponse
    {
        if (isDebugMode() && $request->boolean('bypass_fingerprint')) {
            return response()->json([
                'ok' => true,
                'huellaId' => 'DBG-FP-'.strtoupper(uniqid()),
                'bypassed' => true,
                'message' => 'Sidik jari dilewati (Debug Bypass).',
            ]);
        }

        $hasil = FingerprintService::dariConfig()->enroll();

        if ($hasil === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Sensor sidik jari tidak tersedia atau gagal membaca sidik jari. Coba lagi, atau batal dan selesaikan pendaftaran nanti.',
            ], 503);
        }

        return response()->json([
            'ok' => true,
            'huellaId' => $hasil['huellaId'],
            'message' => 'Sidik jari berhasil didaftarkan.',
        ]);
    }

    /**
     * Simpan pendaftaran lengkap: siswa + akun login + kartu + 3 foto wajah + sidik jari (opsional).
     * SATU transaksi DB — semua berhasil atau tidak ada yang tersimpan.
     * Pada Debug Mode, mendukung bypass granular per device (`bypass_camera`, `bypass_rfid`, `bypass_fingerprint`)
     * yang HANYA mengisi bagian yang kosong tanpa menimpa data asli yang sudah ada.
     */
    public function store(Request $request, FaceTemplateService $faceTemplates): JsonResponse|RedirectResponse
    {
        if (isDebugMode()) {
            $this->applyGranularDebugBypasses($request);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'not_regex:/[<>]/'],
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'nisn' => ['required', 'regex:/^\d{6,20}$/', Rule::unique('students', 'nisn')],
            'setuju' => ['accepted'],
            'card_uid' => ['required', 'string', 'max:50'],
            'fingerprint_id' => ['nullable', 'string', 'max:100'],
            'photos' => ['required', 'array', 'size:3'],
            'photos.*' => ['required', 'string'],
            'face_template' => ['nullable', 'array', 'size:1434'],
            'face_template.*' => ['numeric'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.not_regex' => 'Nama tidak boleh mengandung tag HTML atau karakter < dan >.',
            'school_class_id.required' => 'Kelas wajib dipilih.',
            'school_class_id.exists' => 'Kelas yang dipilih tidak ditemukan.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'nisn.required' => 'NISN wajib diisi.',
            'nisn.regex' => 'NISN hanya boleh berisi angka (6–20 digit).',
            'nisn.unique' => 'NISN tersebut sudah terdaftar. Hubungi guru piket bila ini milik Anda.',
            'setuju.accepted' => 'Centang persetujuan biometrik sebelum mendaftar.',
            'card_uid.required' => 'Tempelkan kartu pelajar Anda ke scanner terlebih dahulu.',
            'photos.required' => 'Tiga foto wajah wajib diambil.',
            'photos.size' => 'Foto wajah wajib pas 3 foto.',
        ]);

        // Kartu yang dipakai tidak boleh milik siswa lain
        $cardUid = trim($validated['card_uid']);
        $kartuTerpakai = RfidCard::with('student')->where('card_uid', $cardUid)->first();
        if ($kartuTerpakai && $kartuTerpakai->student) {
            throw ValidationException::withMessages([
                'card_uid' => 'Kartu dengan UID "'.$cardUid.'" sudah dipakai oleh '.$kartuTerpakai->student->name.'. Gunakan kartu lain atau hubungi guru piket.',
            ]);
        }

        // Decode + validasi KETIGA foto dulu (all-or-nothing)
        $binaries = [];
        foreach ($validated['photos'] as $index => $dataUrl) {
            $binary = $this->decodeDataUrlJpeg($dataUrl);
            if ($binary === null) {
                throw ValidationException::withMessages([
                    'photos.'.$index => 'Foto ke-'.($index + 1).' tidak sah. Ambil ulang foto tersebut.',
                ]);
            }
            $binaries[] = $binary;
        }

        $now = Carbon::now('Asia/Jakarta');
        $fingerprintId = trim((string) ($validated['fingerprint_id'] ?? '')) ?: null;
        $sanitizedTemplate = $faceTemplates->sanitizeVector($validated['face_template'] ?? null);
        $paths = [];

        try {
            $student = DB::transaction(function () use ($validated, $cardUid, $kartuTerpakai, $binaries, $now, $fingerprintId, $sanitizedTemplate, $faceTemplates, &$paths) {
                $siswa = Student::create([
                    'school_class_id' => $validated['school_class_id'],
                    // Siswa baru belum punya NIS dari sekolah; pakai NISN sementara.
                    // Admin dapat memperbaruinya lewat menu Data Siswa.
                    'nis' => $validated['nisn'],
                    'nisn' => $validated['nisn'],
                    'name' => $validated['name'],
                    'gender' => $validated['gender'],
                    'fingerprint_id' => $fingerprintId,
                ]);

                // Buat atau sinkronkan akun login siswa secara otomatis ({NIS}@knbi.sch.id)
                $siswa->syncUserAccount();

                // Kartu: buat baru, atau "adopsi" kartu menganggur yang sudah ada
                if ($kartuTerpakai) {
                    $kartuTerpakai->update(['student_id' => $siswa->id, 'is_active' => true]);
                } else {
                    RfidCard::create(['student_id' => $siswa->id, 'card_uid' => $cardUid, 'is_active' => true]);
                }

                // Tulis 3 foto wajah ke disk privat + catat pendaftaran
                foreach ($binaries as $slot => $binary) {
                    $paths[] = "face-photos/{$siswa->id}/face-{$siswa->id}-".($slot + 1)."-{$now->format('YmdHis')}.jpg";
                }
                foreach ($paths as $i => $path) {
                    Storage::disk('local')->put($path, $binaries[$i]);
                }

                if ($sanitizedTemplate !== null) {
                    foreach ($faceTemplates->storeForStudent($siswa->id, $sanitizedTemplate, 'pendaftaran') as $tplPath) {
                        $paths[] = $tplPath;
                    }
                }

                FaceEnrollment::create([
                    'student_id' => $siswa->id,
                    'photo_1_path' => $paths[0],
                    'photo_2_path' => $paths[1],
                    'photo_3_path' => $paths[2],
                    'consented_at' => $now,
                    'last_reenrolled_at' => null,
                ]);

                return $siswa;
            });

            Cache::forget(FaceTemplateService::CACHE_KEY);
        } catch (ValidationException $e) {
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }

            return back()
                ->withInput()
                ->withErrors(['gagal' => 'Gagal menyimpan pendaftaran. Periksa isian dan coba lagi, atau hubungi guru piket.']);
        }

        $kelas = SchoolClass::find($validated['school_class_id']);
        $successMessage = 'Pendaftaran siswa "'.$student->name.'" berhasil disimpan dan akun login telah aktif.';

        session()->flash('nama', $student->name);
        session()->flash('kelas', $kelas?->name ?? '-');
        session()->flash('status', $successMessage);
        session()->flash('success', $successMessage);

        $redirectUrl = $request->user()?->role === 'admin'
            ? route('admin.siswa.index', array_filter(['kelas' => $kelas?->slug]))
            : route('dashboard');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'redirect' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)
            ->with('status', $successMessage)
            ->with('success', $successMessage);
    }

    /**
     * Terapkan bypass debug secara granular pada request pendaftaran:
     * Hanya mengisi field device yang di-bypass DAN masih kosong,
     * tidak pernah menimpa (overwrite) data riil yang sudah diisi user.
     */
    private function applyGranularDebugBypasses(Request $request): void
    {
        $token = strtoupper(base_convert((string) hrtime(true), 10, 36));

        if ($request->boolean('bypass_rfid')) {
            $currentCard = trim((string) $request->input('card_uid', ''));
            if ($currentCard === '') {
                $request->merge(['card_uid' => 'DBG-CARD-'.$token]);
            }
        }

        if ($request->boolean('bypass_fingerprint')) {
            $currentFp = trim((string) $request->input('fingerprint_id', ''));
            if ($currentFp === '') {
                $request->merge(['fingerprint_id' => 'DBG-FP-'.$token]);
            }
        }

        if ($request->boolean('bypass_camera')) {
            $photos = $request->input('photos');
            $photosArray = is_array($photos) ? array_values($photos) : [];
            $mergedPhotos = [];
            for ($i = 0; $i < 3; $i++) {
                $existing = $photosArray[$i] ?? null;
                if (is_string($existing) && trim($existing) !== '' && $this->decodeDataUrlJpeg($existing) !== null) {
                    $mergedPhotos[$i] = $existing;
                } else {
                    $mergedPhotos[$i] = generateDummyJpegDataUrl('DBG'.($i + 1));
                }
            }
            $request->merge(['photos' => $mergedPhotos]);
        }
    }

    /**
     * Halaman sukses pendaftaran.
     */
    public function berhasil(Request $request): View
    {
        abort_if(! $request->session()->has('nama'), 404);

        return view('pendaftaran.berhasil', [
            'nama' => $request->session()->get('nama'),
            'kelas' => $request->session()->get('kelas', '-'),
        ]);
    }

    /**
     * Decode data URL "data:image/jpeg;base64,..." strict; SOI/EOI JPEG; 100 B–500 KB.
     */
    private function decodeDataUrlJpeg(mixed $dataUrl): ?string
    {
        if (! is_string($dataUrl)) {
            return null;
        }

        $prefix = 'data:image/jpeg;base64,';
        if (! str_starts_with($dataUrl, $prefix)) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strlen($prefix)), true);
        if ($binary === false) {
            return null;
        }

        $size = strlen($binary);
        if ($size < 100 || $size > 500 * 1024) {
            return null;
        }

        if (substr($binary, 0, 2) !== "\xFF\xD8" || substr($binary, -2) !== "\xFF\xD9") {
            return null;
        }

        return $binary;
    }
}
