<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\User;
use App\Services\FingerprintService;
use App\Services\PhotoUploadService;
use App\Services\StudentRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Terminal Pendaftaran Mandiri Siswa Baru (guest, tanpa login).
 */
class PendaftaranController extends Controller
{
    public function __construct(
        private readonly StudentRegistrationService $registrationService,
        private readonly PhotoUploadService $photoService
    ) {}

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
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if (isDebugMode()) {
            $this->applyGranularDebugBypasses($request);
        }

        $validated = $this->validateRegistrationRequest($request);

        try {
            $student = $this->registrationService->register($validated);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            $errors = [
                'gagal' => 'Gagal menyimpan pendaftaran. Periksa isian dan coba lagi, atau hubungi guru piket.',
            ];

            if ($request->expectsJson()) {
                throw ValidationException::withMessages($errors);
            }

            return back()
                ->withInput()
                ->withErrors($errors);
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
     * Validasi seluruh input formulir pendaftaran siswa baru.
     *
     * @return array<string, mixed>
     */
    private function validateRegistrationRequest(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'not_regex:/[<>]/'],
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'nisn' => [
                'required',
                'regex:/^\d{10}$/',
                Rule::unique('students', 'nisn'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (User::where('email', $value.'@knbi.sch.id')->where('role', '!=', 'siswa')->exists()) {
                        $fail('NISN tersebut menghasilkan email akun yang sudah dipakai oleh akun non-siswa. Hubungi guru piket.');
                    }
                },
            ],
            'setuju' => ['accepted'],
            'card_uid' => ['required', 'string', 'max:50'],
            'fingerprint_id' => ['nullable', 'string', 'max:100', Rule::unique('students', 'fingerprint_id')],
            'photos' => ['required', 'array', 'size:3'],
            'photos.*' => ['required'],
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
            'nisn.regex' => 'NISN harus terdiri dari tepat 10 digit angka.',
            'nisn.unique' => 'NISN tersebut sudah terdaftar. Hubungi guru piket bila ini milik Anda.',
            'setuju.accepted' => 'Centang persetujuan biometrik sebelum mendaftar.',
            'card_uid.required' => 'Tempelkan kartu pelajar Anda ke scanner terlebih dahulu.',
            'fingerprint_id.unique' => 'ID sidik jari tersebut sudah terdaftar pada siswa lain.',
            'photos.required' => 'Tiga foto wajah wajib diambil.',
            'photos.size' => 'Foto wajah wajib pas 3 foto.',
        ]);
    }

    /**
     * Terapkan bypass debug secara granular pada field perangkat yang masih kosong.
     */
    private function applyGranularDebugBypasses(Request $request): void
    {
        $token = strtoupper(base_convert((string) hrtime(true), 10, 36));

        if ($request->boolean('bypass_rfid') && trim((string) $request->input('card_uid', '')) === '') {
            $request->merge(['card_uid' => 'DBG-CARD-'.$token]);
        }

        if ($request->boolean('bypass_fingerprint') && trim((string) $request->input('fingerprint_id', '')) === '') {
            $request->merge(['fingerprint_id' => 'DBG-FP-'.$token]);
        }

        if ($request->boolean('bypass_camera')) {
            $request->merge(['photos' => $this->photoService->mergeDebugBypassPhotos($request->all()['photos'] ?? null)]);
        }
    }
}
