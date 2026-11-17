<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\FaceTemplateService;
use App\Services\KioskAttendanceService;
use App\Services\PhotoUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KioskController extends Controller
{
    public const FACE_TEMPLATE_VERSION = FaceTemplateService::TEMPLATE_VERSION;

    public function __construct(
        private readonly KioskAttendanceService $kioskService = new KioskAttendanceService(new PhotoUploadService),
        private readonly FaceTemplateService $faceTemplates = new FaceTemplateService,
        private readonly PhotoUploadService $photoService = new PhotoUploadService
    ) {}

    /**
     * Layar Kiosk Gerbang Tunggal (absen masuk/pulang lewat kartu / wajah / sidik jari).
     */
    public function gerbang(): View
    {
        $debugStudents = isDebugMode()
            ? Student::with('schoolClass:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'nis', 'school_class_id'])
            : collect();

        return view('kiosk.gerbang', [
            'isMockFingerprint' => config('fingerprint.driver', 'mock') === 'mock',
            'debugStudents' => $debugStudents,
        ]);
    }

    /**
     * Simulasi absensi developer mode tanpa perangkat fisik (hanya aktif saat DEBUG_MODE=true).
     */
    public function debugSimulate(Request $request): JsonResponse
    {
        abort_unless(isDebugMode(), 404);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'channel' => ['nullable', 'string', 'in:debug,kartu,wajah,fingerprint'],
            'photo' => ['nullable', 'string', 'max:400000'],
            'bypass_cooldown' => ['nullable', 'boolean'],
        ]);

        return $this->kioskService->processDebugSimulate(
            (int) $validated['student_id'],
            (string) ($validated['channel'] ?? 'debug'),
            $validated['photo'] ?? null,
            $request->boolean('bypass_cooldown')
        );
    }

    /**
     * Proses pembacaan tap kartu RFID di Kiosk Gerbang.
     */
    public function tap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_uid' => ['required', 'string', 'max:50'],
            'photo' => ['nullable', 'string', 'max:400000'],
        ]);

        return $this->kioskService->processCardTap(
            trim($validated['card_uid']),
            $validated['photo'] ?? null,
            $request->boolean('direct'),
            isDebugMode() && $request->boolean('bypass_face_verify'),
            isDebugMode() && $request->boolean('bypass_cooldown')
        );
    }

    /**
     * Ambil kumpulan template landmark wajah (1434 float) semua siswa terdaftar.
     */
    public function faceTemplates(Request $request): JsonResponse
    {
        return response()->json(
            $this->faceTemplates->getTemplatesForKiosk($request->boolean('fresh'), $this->photoService)
        );
    }

    /**
     * Sinkronkan hasil ekstraksi vektor wajah 1434 float dari foto pendaftaran yang sudah ada.
     */
    public function syncFaceTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'status' => ['nullable', 'string', 'in:ok,unextractable'],
            'template' => ['nullable', 'array', 'size:1434'],
            'template.*' => ['numeric'],
        ]);

        $result = $this->faceTemplates->syncForStudent(
            (int) $validated['student_id'],
            (string) ($validated['status'] ?? 'ok'),
            $validated['template'] ?? null
        );

        return response()->json($result['body'], $result['httpStatus']);
    }

    /**
     * Endpoint check-in mandiri via Face ID (1:N matching di browser).
     */
    public function faceCheckin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'similarity' => ['required', 'numeric', 'min:0', 'max:1'],
            'photo' => ['nullable', 'string', 'max:400000'],
        ]);

        return $this->kioskService->processFaceCheckin(
            (int) $validated['student_id'],
            (float) $validated['similarity'],
            $validated['photo'] ?? null
        );
    }

    /**
     * Proses verifikasi wajah 1:1 di Kiosk setelah tap kartu.
     */
    public function faceVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'match_score' => ['required', 'numeric'],
            'photo' => ['nullable', 'string', 'max:400000'],
        ]);

        return $this->kioskService->processFaceVerify(
            (int) $validated['student_id'],
            (float) $validated['match_score'],
            $validated['photo'] ?? null
        );
    }

    /**
     * Proses identifikasi sidik jari di Kiosk Gerbang via bridge/mock.
     */
    public function identificarSidikJari(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['nullable', 'string', 'max:400000'],
            'auto' => ['nullable'],
        ]);

        return $this->kioskService->identifyByFingerprint(
            filter_var($request->input('auto'), FILTER_VALIDATE_BOOLEAN),
            $request->input('photo')
        );
    }

    /**
     * Alias internal menuju layanan inti absensi gerbang (menjaga kompatibilitas kontrak KioskController).
     */
    private function prosesAbsensi(Student $student, ?string $photo, string $device, bool $bypassCooldown = false): JsonResponse
    {
        return $this->kioskService->recordAttendance($student, $photo, $device, $bypassCooldown);
    }
}
