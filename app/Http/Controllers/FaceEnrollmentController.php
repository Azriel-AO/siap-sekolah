<?php

namespace App\Http\Controllers;

use App\Models\FaceEnrollment;
use App\Models\Student;
use App\Services\FaceEnrollmentService;
use App\Services\FaceTemplateService;
use App\Services\PhotoUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FaceEnrollmentController extends Controller
{
    public function __construct(
        private readonly FaceEnrollmentService $enrollmentService,
        private readonly FaceTemplateService $faceTemplates,
        private readonly PhotoUploadService $photoService
    ) {}

    /**
     * Halaman pendaftaran wajah mandiri (hanya peran siswa).
     */
    public function show(Request $request): View
    {
        $this->pastikanSiswa($request);

        $student = $this->siswaMilikAkun($request);
        $enrollment = $student?->faceEnrollment;
        $wajahTervalidasi = $this->pendaftaranValid($enrollment);

        return view('face-enrollment.show', [
            'student' => $student,
            'enrollment' => $enrollment,
            'wajahTervalidasi' => $wajahTervalidasi,
            'photosEmbedded' => $wajahTervalidasi ? $this->fotoEmbedded($enrollment) : [],
            'tanggalDaftar' => $wajahTervalidasi ? $enrollment->created_at->translatedFormat('l, d F Y H.i') : null,
            'tanggalDaftarUlang' => $wajahTervalidasi ? $enrollment->last_reenrolled_at?->translatedFormat('l, d F Y H.i') : null,
        ]);
    }

    /**
     * Status pendaftaran wajah milik siswa yang login (JSON, tanpa reload).
     */
    public function status(Request $request): JsonResponse
    {
        $this->pastikanSiswa($request);

        $student = $this->siswaMilikAkun($request);
        $enrollment = $student?->faceEnrollment;

        if (! $this->pendaftaranValid($enrollment)) {
            return response()->json([
                'terdaftar' => false,
                'status_label' => 'Belum Terdaftar',
                'tanggal_daftar' => null,
                'tanggal_daftar_ulang' => null,
                'photos' => [],
                'message' => 'Anda belum mendaftarkan wajah.',
            ]);
        }

        return response()->json([
            'terdaftar' => true,
            'status_label' => 'Terdaftar',
            'tanggal_daftar' => $enrollment->created_at->translatedFormat('l, d F Y H.i'),
            'tanggal_daftar_ulang' => $enrollment->last_reenrolled_at?->translatedFormat('l, d F Y H.i'),
            'photos' => $this->fotoEmbedded($enrollment),
            'message' => 'Wajah Anda sudah terdaftar.',
        ]);
    }

    /**
     * Simpan pendaftaran wajah (tepat 3 foto + persetujuan orang tua/wali).
     */
    public function store(Request $request): JsonResponse
    {
        $this->pastikanSiswa($request);

        $student = $this->siswaMilikAkun($request);
        if (! $student) {
            return $this->gagal('Akun siswa Anda belum tertaut dengan data siswa. Hubungi petugas sekolah.', 422);
        }

        $consent = $request->input('consent');
        if ($consent !== true && $consent !== 'true' && $consent !== 1) {
            return $this->gagal('Anda harus menyetujui pernyataan izin orang tua/wali sebelum menyimpan.', 422);
        }

        $photos = $request->all()['photos'] ?? $request->input('photos');
        if (isDebugMode() && $request->boolean('bypass_camera')) {
            $photos = $this->photoService->mergeDebugBypassPhotos($photos);
        }

        if (! is_array($photos) || count($photos) !== 3) {
            $jumlah = is_array($photos) ? count($photos) : 0;

            return $this->gagal("Foto wajah wajib lengkap 3 foto. Saat ini baru {$jumlah} foto sah.", 422);
        }

        $sanitizedTemplate = $this->faceTemplates->sanitizeVector($request->input('face_template'));
        $result = $this->enrollmentService->saveEnrollment($student, $photos, $sanitizedTemplate);

        if (! $result['ok']) {
            return $this->gagal((string) $result['message'], 422);
        }

        $enrollment = $result['enrollment'];

        return response()->json([
            'success' => true,
            'status_label' => 'Terdaftar',
            'message' => 'Pendaftaran wajah berhasil disimpan.',
            'tanggal_daftar' => $enrollment->created_at->translatedFormat('l, d F Y H.i'),
            'tanggal_daftar_ulang' => $enrollment->last_reenrolled_at?->translatedFormat('l, d F Y H.i'),
        ]);
    }

    private function pastikanSiswa(Request $request): void
    {
        if ($request->user()->role !== 'siswa') {
            abort(403, 'Halaman ini hanya untuk siswa.');
        }
    }

    private function siswaMilikAkun(Request $request): ?Student
    {
        return Student::where('user_id', $request->user()->id)->first()
            ?? Student::first();
    }

    private function pendaftaranValid(?FaceEnrollment $enrollment): bool
    {
        if (! $enrollment || ! $enrollment->photo_1_path || ! $enrollment->photo_2_path || ! $enrollment->photo_3_path) {
            return false;
        }

        return Storage::disk('local')->exists($enrollment->photo_1_path)
            && Storage::disk('local')->exists($enrollment->photo_2_path)
            && Storage::disk('local')->exists($enrollment->photo_3_path);
    }

    /**
     * @return list<string|null>
     */
    private function fotoEmbedded(FaceEnrollment $enrollment): array
    {
        return [
            $this->photoService->readLocalPhotoDataUrl($enrollment->photo_1_path),
            $this->photoService->readLocalPhotoDataUrl($enrollment->photo_2_path),
            $this->photoService->readLocalPhotoDataUrl($enrollment->photo_3_path),
        ];
    }

    private function gagal(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
