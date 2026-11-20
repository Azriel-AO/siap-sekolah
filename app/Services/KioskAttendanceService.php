<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Layanan pemrosesan absensi siswa pada Kiosk Gerbang (Kartu RFID, Face ID, Sidik Jari).
 */
class KioskAttendanceService
{
    public function __construct(
        private readonly PhotoUploadService $photoService,
        private readonly FaceTemplateService $faceTemplates = new FaceTemplateService
    ) {}

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
            $isAdmin = auth()->user()?->role === 'admin';
            $gateCheck = $this->checkPendingFaceVerify($student, $isAdmin ? $facePhotos : [], $bypassCooldown);
            if ($gateCheck !== null) {
                return $gateCheck;
            }
        }

        $response = $this->recordAttendance($student, $photo, 'gerbang-kartu', $bypassCooldown);

        return $this->withStudentCardMetadata($response, $student, 'Kartu');
    }

    /**
     * Proses absensi check-in langsung lewat Face ID 1:N dengan perhitungan Cosine Similarity di server.
     *
     * @param  list<float|int>  $liveVector
     */
    public function processFaceCheckin(int $studentId, array $liveVector, ?string $photo): JsonResponse
    {
        $cleanLive = $this->faceTemplates->sanitizeVector($liveVector);
        $storedVector = $this->faceTemplates->readVector($studentId);

        if ($cleanLive === null || $storedVector === null) {
            return response()->json([
                'success' => false,
                'type' => 'face_mismatch',
                'message' => 'Template wajah siswa belum tersedia atau vektor tidak valid. Silakan coba lagi atau gunakan kartu/sidik jari.',
            ], 422);
        }

        $sum = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = FaceTemplateService::VECTOR_LENGTH;

        for ($i = 0; $i < $count; $i++) {
            $a = (float) $cleanLive[$i];
            $b = (float) $storedVector[$i];
            $sum += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        $similarity = $sum / max(sqrt($normA) * sqrt($normB), 1e-12);

        if (! is_finite($similarity) || $similarity < 0.75) {
            return response()->json([
                'success' => false,
                'type' => 'face_mismatch',
                'message' => 'Kemiripan wajah tidak memenuhi syarat (minimal 75%). Silakan coba lagi atau gunakan kartu/sidik jari.',
            ], 422);
        }

        $student = Student::with(['schoolClass', 'rfidCard'])->findOrFail($studentId);
        $response = $this->recordAttendance($student, $photo, 'face');

        return $this->withStudentCardMetadata($response, $student, 'Wajah', [
            'similarity' => round(min(1.0, max(-1.0, $similarity)), 4),
        ]);
    }

    /**
     * Proses verifikasi wajah 1:1 setelah tap kartu dengan token anti-replay wajib.
     */
    public function processFaceVerify(int $studentId, float $score, ?string $photo, ?string $verifyToken = null): JsonResponse
    {
        if ($verifyToken === null || trim($verifyToken) === '') {
            return response()->json([
                'success' => false,
                'type' => 'replay_detected',
                'message' => 'Token verifikasi wajah wajib disertakan. Silakan tap ulang kartu.',
            ], 422);
        }

        $consumed = Cache::pull("kiosk-verify-token:{$studentId}:{$verifyToken}");
        Cache::forget("kiosk-verify-pending:{$studentId}");
        if ($consumed !== true) {
            return response()->json([
                'success' => false,
                'type' => 'replay_detected',
                'message' => 'Sesi verifikasi wajah tidak valid atau sudah digunakan. Silakan tap ulang kartu.',
            ], 422);
        }

        if (! is_finite($score) || $score <= 0.85 || $score > 1.0) {
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
     * Logika inti absensi masuk/pulang dengan kunci atomik anti-TOCTOU & transaksi DB.
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
        $lockKey = "kiosk-attendance:{$student->id}:{$today}";

        return Cache::lock($lockKey, 10)->block(5, function () use (
            $student,
            $schoolClass,
            $photo,
            $device,
            $bypassCooldown,
            $now,
            $today,
            $currentTime
        ): JsonResponse {
            // SQLite (WAL) + transaksi DEFERRED pada PHP 8.3 dapat gagal instan dengan
            // "database is locked" (BUSY_SNAPSHOT) saat banyak transaksi tulis paralel —
            // busy timeout tidak berlaku untuk kasus ini. Retry dengan backoff memberi
            // kesempatan snapshot segar; pembersihan foto per-attempt ditangani di dalam.
            return retry(8, function () use (
                $student,
                $schoolClass,
                $photo,
                $device,
                $bypassCooldown,
                $now,
                $today,
                $currentTime
            ): JsonResponse {
                $pendingPhotoPath = null;

                try {
                    return DB::transaction(function () use (
                        $student,
                        $schoolClass,
                        $photo,
                        $device,
                        $bypassCooldown,
                        $now,
                        $today,
                        $currentTime,
                        &$pendingPhotoPath
                    ): JsonResponse {
                        $attendance = Attendance::where('student_id', $student->id)
                            ->where('date', $today)
                            ->first();

                        if (! $bypassCooldown && $this->isInCooldown($attendance, $now)) {
                            return $this->cooldownResponse($student->name);
                        }

                        if (! $attendance) {
                            $status = ($currentTime <= $schoolClass->entry_time) ? 'HADIR' : 'TERLAMBAT';
                            $photoPath = $this->photoService->storeEvidencePhoto($photo, $student, 'masuk', $now);
                            $pendingPhotoPath = $photoPath;

                            try {
                                Attendance::create([
                                    'student_id' => $student->id,
                                    'school_class_id' => $schoolClass->id,
                                    'date' => $today,
                                    'check_in_at' => $currentTime,
                                    'status' => $status,
                                    'last_tap_at' => $now,
                                    'kiosk_device' => $device,
                                    'photo_in_path' => $photoPath,
                                ]);
                            } catch (QueryException $e) {
                                if ($photoPath) {
                                    Storage::disk('local')->delete($photoPath);
                                    $pendingPhotoPath = null;
                                }

                                if (! str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                                    throw $e;
                                }

                                return $this->cooldownResponse($student->name);
                            }

                            return response()->json([
                                'success' => true,
                                'type' => 'check_in',
                                'student_name' => $student->name,
                                'nisn' => $student->nisn,
                                'kelas' => $schoolClass->name,
                                'status' => $status,
                                'status_label' => ($status === 'HADIR') ? 'Hadir Tepat Waktu' : 'Terlambat',
                                'time' => $currentTime,
                                'message' => "Selamat datang, {$student->name}!",
                            ]);
                        }

                        if (! $attendance->check_out_at) {
                            $photoOutPath = $this->photoService->storeEvidencePhoto($photo, $student, 'pulang', $now);
                            $pendingPhotoPath = $photoOutPath;

                            try {
                                $attendance->update([
                                    'check_out_at' => $currentTime,
                                    'last_tap_at' => $now,
                                    'photo_out_path' => $photoOutPath ?? $attendance->photo_out_path,
                                ]);
                            } catch (QueryException $e) {
                                if ($photoOutPath) {
                                    Storage::disk('local')->delete($photoOutPath);
                                    $pendingPhotoPath = null;
                                }

                                throw $e;
                            }

                            return response()->json([
                                'success' => true,
                                'type' => 'check_out',
                                'student_name' => $student->name,
                                'nisn' => $student->nisn,
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
                    });
                } catch (Throwable $e) {
                    if ($pendingPhotoPath) {
                        Storage::disk('local')->delete($pendingPhotoPath);
                    }

                    throw $e;
                }
            }, 300, function (Throwable $e): bool {
                return $e instanceof QueryException && str_contains($e->getMessage(), 'database is locked');
            });
        });
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

        $verifyToken = Str::random(40);
        Cache::put("kiosk-verify-token:{$student->id}:{$verifyToken}", true, now()->addSeconds(90));
        Cache::put("kiosk-verify-pending:{$student->id}", $verifyToken, now()->addSeconds(90));

        return response()->json([
            'success' => true,
            'requires_face_verify' => true,
            'type' => 'face_verification_required',
            'verify_token' => $verifyToken,
            'student_id' => $student->id,
            'student_name' => $student->name,
            'nisn' => $student->nisn,
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
