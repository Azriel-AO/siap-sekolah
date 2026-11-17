<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

/**
 * Layanan pemrosesan absensi siswa pada Kiosk Gerbang (Kartu RFID, Face ID, Sidik Jari).
 */
class KioskAttendanceService
{
    public function __construct(private readonly PhotoUploadService $photoService) {}

    /**
     * Proses tap kartu RFID dan tentukan apakah lanjut verifikasi wajah atau langsung catat absensi.
     */
    public function processCardTap(
        string $cardUid,
        ?string $photo,
        bool $direct,
        bool $bypassFaceVerify,
        bool $bypassCooldown
    ): JsonResponse {
        $card = RfidCard::with(['student.schoolClass', 'student.faceEnrollment', 'student.rfidCard'])
            ->where('card_uid', $cardUid)
            ->where('is_active', true)
            ->first();

        if (! $card || ! $card->student) {
            return response()->json([
                'success' => false,
                'type' => 'not_found',
                'message' => 'Kartu tidak dikenal. Silakan daftar dulu.',
                'registration_url' => route('pendaftaran.index'),
            ], 404);
        }

        $student = $card->student;
        $facePhotos = $this->photoService->readStudentEnrollmentPhotos($student);

        if (! $direct && ! $bypassFaceVerify && count($facePhotos) === 3) {
            $gateCheck = $this->checkPendingFaceVerify($student, $facePhotos, $bypassCooldown);
            if ($gateCheck !== null) {
                return $gateCheck;
            }
        }

        $response = $this->recordAttendance($student, $photo, 'gerbang-kartu', $bypassCooldown);

        return $this->withStudentCardMetadata($response, $student, 'Kartu');
    }

    /**
     * Proses absensi check-in langsung lewat Face ID 1:N.
     */
    public function processFaceCheckin(int $studentId, float $similarity, ?string $photo): JsonResponse
    {
        if ($similarity < 0.75) {
            return response()->json([
                'success' => false,
                'type' => 'face_mismatch',
                'message' => 'Kemiripan wajah tidak memenuhi syarat (minimal 75%). Silakan coba lagi atau gunakan kartu/sidik jari.',
            ], 422);
        }

        $student = Student::with(['schoolClass', 'rfidCard'])->findOrFail($studentId);
        $response = $this->recordAttendance($student, $photo, 'face');

        return $this->withStudentCardMetadata($response, $student, 'Wajah', [
            'similarity' => round($similarity, 4),
        ]);
    }

    /**
     * Proses verifikasi wajah 1:1 setelah tap kartu.
     */
    public function processFaceVerify(int $studentId, float $score, ?string $photo): JsonResponse
    {
        if ($score <= 0.85 || $score > 1.0) {
            return response()->json([
                'success' => false,
                'type' => 'face_mismatch',
                'message' => 'Wajah tidak cocok. Silakan coba lagi atau gunakan sidik jari.',
            ], 422);
        }

        $student = Student::with(['schoolClass', 'rfidCard'])->findOrFail($studentId);
        $response = $this->recordAttendance($student, $photo, 'gerbang-kartu');

        return $this->withStudentCardMetadata($response, $student, null, [
            'face_verified' => true,
            'match_score' => round($score, 4),
        ]);
    }

    /**
     * Proses identifikasi sidik jari dari sensor bridge maupun driver mock.
     */
    public function identifyByFingerprint(bool $auto, ?string $photo): JsonResponse
    {
        $hasil = FingerprintService::dariConfig()->identifyDetailed($auto);

        if ($hasil['status'] === 'bridge_mati') {
            return response()->json([
                'success' => false,
                'type' => 'bridge_mati',
                'message' => 'Sensor belum aktif — hadir dengan tap kartu',
            ], 503);
        }

        if ($hasil['status'] === 'sensor_timeout') {
            return response()->json([
                'success' => false,
                'type' => 'sensor_timeout',
                'message' => 'Sensor sedang menunggu jari — coba tempelkan jari lebih lama',
            ], 200);
        }

        if ($hasil['status'] === 'sensor_idle') {
            return response()->json([
                'success' => false,
                'type' => 'sensor_idle',
                'message' => 'Menunggu sidik jari ditempelkan pada sensor.',
            ], 200);
        }

        $huellaId = $hasil['huellaId'];
        if ($hasil['status'] === 'fingerprint_unknown' || $huellaId === null) {
            return $this->fingerprintUnknownResponse();
        }

        $student = Student::with(['schoolClass', 'rfidCard'])->where('fingerprint_id', $huellaId)->first();
        if (! $student) {
            return $this->fingerprintUnknownResponse();
        }

        $response = $this->recordAttendance($student, $photo, 'gerbang-fingerprint');

        return $this->withStudentCardMetadata($response, $student, 'Sidik Jari');
    }

    /**
     * Simulasi absensi mode debug untuk developer tanpa perangkat fisik.
     */
    public function processDebugSimulate(
        int $studentId,
        string $channel,
        ?string $photo,
        bool $bypassCooldown
    ): JsonResponse {
        $student = Student::with(['schoolClass', 'rfidCard'])->findOrFail($studentId);

        [$device, $methodLabel] = match ($channel) {
            'kartu' => ['gerbang-kartu', 'Kartu'],
            'wajah' => ['face', 'Wajah'],
            'fingerprint' => ['gerbang-fingerprint', 'Sidik Jari'],
            default => ['debug', 'debug'],
        };

        $response = $this->recordAttendance($student, $photo, $device, $bypassCooldown);

        return $this->withStudentCardMetadata($response, $student, $methodLabel);
    }

    /**
     * Logika inti absensi masuk/pulang memakai waktu server mutlak dan cooldown 60 detik.
     */
    public function recordAttendance(
        Student $student,
        ?string $photo,
        string $device,
        bool $bypassCooldown = false
    ): JsonResponse {
        if (! $student->relationLoaded('schoolClass')) {
            $student->load('schoolClass');
        }

        $schoolClass = $student->schoolClass;
        if (! $schoolClass) {
            return response()->json([
                'success' => false,
                'type' => 'class_not_found',
                'student_name' => $student->name,
                'message' => 'Data kelas siswa tidak ditemukan. Hubungi admin sekolah.',
            ], 422);
        }

        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        $attendance = Attendance::where('student_id', $student->id)
            ->where('date', $today)
            ->first();

        if (! $bypassCooldown && $this->isInCooldown($attendance, $now)) {
            return $this->cooldownResponse($student->name);
        }

        if (! $attendance) {
            $status = ($currentTime <= $schoolClass->entry_time) ? 'HADIR' : 'TERLAMBAT';

            try {
                Attendance::create([
                    'student_id' => $student->id,
                    'school_class_id' => $schoolClass->id,
                    'date' => $today,
                    'check_in_at' => $currentTime,
                    'status' => $status,
                    'last_tap_at' => $now,
                    'kiosk_device' => $device,
                    'photo_in_path' => $this->photoService->storeEvidencePhoto($photo, $student, 'masuk', $now),
                ]);
            } catch (QueryException) {
                return $this->cooldownResponse($student->name);
            }

            return response()->json([
                'success' => true,
                'type' => 'check_in',
                'student_name' => $student->name,
                'nis' => $student->nis,
                'kelas' => $schoolClass->name,
                'status' => $status,
                'status_label' => ($status === 'HADIR') ? 'Hadir Tepat Waktu' : 'Terlambat',
                'time' => $currentTime,
                'message' => "Selamat datang, {$student->name}!",
            ]);
        }

        if (! $attendance->check_out_at) {
            $attendance->update([
                'check_out_at' => $currentTime,
                'last_tap_at' => $now,
                'photo_out_path' => $this->photoService->storeEvidencePhoto($photo, $student, 'pulang', $now) ?? $attendance->photo_out_path,
            ]);

            return response()->json([
                'success' => true,
                'type' => 'check_out',
                'student_name' => $student->name,
                'nis' => $student->nis,
                'kelas' => $schoolClass->name,
                'status' => $attendance->status,
                'status_label' => 'Absen Pulang Berhasil',
                'time' => $currentTime,
                'message' => "Selamat pulang, {$student->name}!",
            ]);
        }

        return response()->json([
            'success' => false,
            'type' => 'already_completed',
            'student_name' => $student->name,
            'kelas' => $schoolClass->name,
            'message' => "Siswa {$student->name} sudah menyelesaikan absen masuk dan pulang hari ini.",
        ], 200);
    }

    /**
     * Periksa cooldown & status tap ganda sebelum menampilkan antarmuka verifikasi wajah 1:1.
     *
     * @param  list<string>  $facePhotos
     */
    private function checkPendingFaceVerify(Student $student, array $facePhotos, bool $bypassCooldown): JsonResponse
    {
        $cardLabel = $student->card_label;
        $now = Carbon::now('Asia/Jakarta');
        $attendance = Attendance::where('student_id', $student->id)
            ->where('date', $now->toDateString())
            ->first();

        if (! $bypassCooldown && $this->isInCooldown($attendance, $now)) {
            return response()->json([
                'success' => false,
                'type' => 'cooldown',
                'student_name' => $student->name,
                'card_label' => $cardLabel,
                'message' => 'Kartu baru saja dicatat. Silakan tunggu sebentar.',
            ], 429);
        }

        if ($attendance && $attendance->check_out_at) {
            return response()->json([
                'success' => false,
                'type' => 'already_completed',
                'student_name' => $student->name,
                'kelas' => $student->schoolClass?->name,
                'card_label' => $cardLabel,
                'message' => "Siswa {$student->name} sudah menyelesaikan absen masuk dan pulang hari ini.",
            ], 200);
        }

        return response()->json([
            'success' => true,
            'requires_face_verify' => true,
            'type' => 'face_verification_required',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'nis' => $student->nis,
            'kelas' => $student->schoolClass?->name,
            'card_label' => $cardLabel,
            'face_photos' => $facePhotos,
            'message' => $cardLabel !== null
                ? "Halo, {$student->name} ({$cardLabel}) — silakan verifikasi wajah di kamera."
                : "Halo, {$student->name} — silakan verifikasi wajah di kamera.",
        ]);
    }

    /**
     * Tambahkan label kartu siswa dan data tambahan ke respons JSON kiosk.
     *
     * @param  array<string, mixed>  $extraData
     */
    private function withStudentCardMetadata(
        JsonResponse $response,
        Student $student,
        ?string $methodLabel = null,
        array $extraData = []
    ): JsonResponse {
        $data = $response->getData(true);

        if ($methodLabel !== null) {
            $data['method'] = $methodLabel;
        }

        foreach ($extraData as $key => $value) {
            $data[$key] = $value;
        }

        $cardLabel = $student->card_label;
        if ($cardLabel !== null) {
            $data['card_label'] = $cardLabel;
            if (! empty($data['success'])) {
                $data['message'] = "Halo, {$student->name} ({$cardLabel})";
            }
        }

        $response->setData($data);

        return $response;
    }

    private function isInCooldown(?Attendance $attendance, Carbon $now): bool
    {
        return $attendance !== null
            && $attendance->last_tap_at !== null
            && abs($now->diffInSeconds($attendance->last_tap_at)) < 60;
    }

    private function cooldownResponse(string $studentName): JsonResponse
    {
        return response()->json([
            'success' => false,
            'type' => 'cooldown',
            'student_name' => $studentName,
            'message' => 'Kartu baru saja dicatat. Silakan tunggu sebentar.',
        ], 429);
    }

    private function fingerprintUnknownResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'type' => 'fingerprint_unknown',
            'message' => 'Sidik jari tidak dikenali. Daftar dulu bila belum terdaftar.',
            'registration_url' => route('pendaftaran.index'),
        ], 200);
    }
}
