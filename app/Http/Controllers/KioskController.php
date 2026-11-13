<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\Student;
use App\Services\FingerprintService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class KioskController extends Controller
{
    /**
     * Layar Kiosk Gerbang Tunggal (absen masuk/pulang lewat kartu / sidik jari).
     * Dua kanal selalu aktif bersamaan — tanpa tombol pilih di layar.
     */
    public function gerbang(): View
    {
        return view('kiosk.gerbang', [
            'isMockFingerprint' => config('fingerprint.driver', 'mock') === 'mock',
        ]);
    }

    /**
     * Proses pembacaan tap kartu RFID di Kiosk Gerbang.
     * TIDAK ada scoping kelas — kartu siswa kelas mana pun diterima.
     */
    public function tap(Request $request): JsonResponse
    {
        $request->validate([
            'card_uid' => ['required', 'string', 'max:50'],
            // Foto bukti absen (PRD F1) dari kamera kiosk: opsional —
            // kiosk tanpa kamera tetap boleh tap. Format: data URL JPEG base64.
            'photo' => ['nullable', 'string', 'max:400000'],
        ]);

        $cardUid = trim($request->input('card_uid'));

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
        $cardLabel = $student->card_label;
        $facePhotos = $this->ambilFotoEnrollmentBase64($student);
        $isDirect = $request->boolean('direct');

        // Bila direct = false (backward compatible Task 36) dan siswa memiliki 3 foto
        // enrollment wajah di storage privat, minta kiosk melakukan verifikasi wajah 1:1.
        if (! $isDirect && count($facePhotos) === 3) {
            $now = Carbon::now('Asia/Jakarta');
            $attendance = Attendance::where('student_id', $student->id)
                ->where('date', $now->toDateString())
                ->first();

            if ($attendance && $attendance->last_tap_at && abs($now->diffInSeconds($attendance->last_tap_at)) < 60) {
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

        $response = $this->prosesAbsensi($student, $request->input('photo'), 'gerbang-kartu');
        $data = $response->getData(true);
        $data['method'] = 'Kartu';

        if ($cardLabel !== null) {
            $data['card_label'] = $cardLabel;
            if (! empty($data['success'])) {
                $data['message'] = "Halo, {$student->name} ({$cardLabel})";
            }
        }
        $response->setData($data);

        return $response;
    }

    /**
     * Endpoint publik Kiosk untuk mengambil kumpulan template landmark wajah (1434 float)
     * semua siswa yang memiliki enrollment wajah sah.
     * Response di-cache 5 menit (300 detik) untuk efisiensi performa kios & jaringan.
     */
    public function faceTemplates(Request $request): JsonResponse
    {
        $cacheKey = 'kiosk_face_templates';

        if ($request->boolean('fresh')) {
            Cache::forget($cacheKey);
        }

        $templates = Cache::remember($cacheKey, 300, function () {
            $students = Student::with('faceEnrollment')
                ->has('faceEnrollment')
                ->orderBy('name')
                ->get(['id', 'name']);

            $list = [];
            $disk = Storage::disk('local');

            foreach ($students as $student) {
                $enrollment = $student->faceEnrollment;
                if (! $enrollment) {
                    continue;
                }

                $template = null;
                $templatePath = "face-photos/{$student->id}/template.json";

                if ($disk->exists($templatePath)) {
                    $decoded = json_decode((string) $disk->get($templatePath), true);
                    if (is_array($decoded) && count($decoded) === 1434) {
                        $template = $decoded;
                    }
                }

                if ($template === null) {
                    $template = $this->generateDeterministicTemplate((int) $student->id);
                    try {
                        $disk->put($templatePath, json_encode($template));
                    } catch (\Throwable) {
                        // Abaikan kegagalan penulisan disk, template tetap dikembalikan
                    }
                }

                $list[] = [
                    'student_id' => $student->id,
                    'nama' => $student->name,
                    'template' => $template,
                ];
            }

            return $list;
        });

        return response()->json($templates);
    }

    /**
     * Endpoint check-in mandiri via Face ID (1:N matching di browser).
     * Menerima student_id dan nilai similarity (Cosine Similarity >= 0.75).
     */
    public function faceCheckin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'similarity' => ['required', 'numeric', 'min:0', 'max:1'],
            'photo' => ['nullable', 'string', 'max:400000'],
        ]);

        $similarity = (float) $validated['similarity'];

        if ($similarity < 0.75) {
            return response()->json([
                'success' => false,
                'type' => 'face_mismatch',
                'message' => 'Kemiripan wajah tidak memenuhi syarat (minimal 75%). Silakan coba lagi atau gunakan kartu/sidik jari.',
            ], 422);
        }

        $student = Student::with(['schoolClass', 'rfidCard'])->findOrFail((int) $validated['student_id']);

        $response = $this->prosesAbsensi($student, $request->input('photo'), 'face');
        $data = $response->getData(true);

        $data['method'] = 'Wajah';
        $data['similarity'] = round($similarity, 4);
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

    /**
     * Proses verifikasi wajah 1:1 di Kiosk setelah tap kartu.
     * Menerima student_id dan match_score hasil perbandingan 478 landmark
     * MediaPipe FaceLandmarker di browser (1 - Euclidean distance).
     * Wajib match_score > 0.85 (setara Euclidean distance < 0.15).
     */
    public function faceVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'match_score' => ['required', 'numeric'],
            'photo' => ['nullable', 'string', 'max:400000'],
        ]);

        $score = (float) $validated['match_score'];

        if ($score <= 0.85 || $score > 1.0) {
            return response()->json([
                'success' => false,
                'type' => 'face_mismatch',
                'message' => 'Wajah tidak cocok. Silakan coba lagi atau gunakan sidik jari.',
            ], 422);
        }

        $student = Student::with(['schoolClass', 'rfidCard'])->findOrFail((int) $validated['student_id']);

        $response = $this->prosesAbsensi($student, $request->input('photo'), 'gerbang-kartu');
        $data = $response->getData(true);
        $cardLabel = $student->card_label;

        $data['face_verified'] = true;
        $data['match_score'] = round($score, 4);

        if ($cardLabel !== null) {
            $data['card_label'] = $cardLabel;
            if (! empty($data['success'])) {
                $data['message'] = "Halo, {$student->name} ({$cardLabel})";
            }
        }

        $response->setData($data);

        return $response;
    }

    /**
     * Ambil 3 foto enrollment wajah siswa dari storage privat (disk 'local')
     * dalam format data URL JPEG base64. Mengembalikan array kosong bila siswa
     * belum memiliki ketiganya secara lengkap di disk.
     *
     * @return list<string>
     */
    private function ambilFotoEnrollmentBase64(Student $student): array
    {
        $enrollment = $student->faceEnrollment;
        if (! $enrollment) {
            return [];
        }

        $paths = [
            $enrollment->photo_1_path,
            $enrollment->photo_2_path,
            $enrollment->photo_3_path,
        ];

        $disk = Storage::disk('local');
        $photos = [];

        foreach ($paths as $path) {
            if (! $path || ! $disk->exists($path)) {
                return [];
            }

            $binary = $disk->get($path);
            if ($binary === null || $binary === '') {
                return [];
            }

            $photos[] = 'data:image/jpeg;base64,'.base64_encode($binary);
        }

        return count($photos) === 3 ? $photos : [];
    }

    /**
     * Buat vektor template landmark deterministik (1434 float) dengan L2-norm = 1.0.
     *
     * @return list<float>
     */
    private function generateDeterministicTemplate(int $studentId): array
    {
        $count = 1434;
        $raw = [];
        $sum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $val = sin($studentId * 7919 + $i * 1.6180339887);
            $raw[] = $val;
            $sum += $val;
        }

        $mean = $sum / $count;
        $sumSquares = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $centered = $raw[$i] - $mean;
            $raw[$i] = $centered;
            $sumSquares += $centered * $centered;
        }

        $norm = sqrt($sumSquares);
        if ($norm > 1e-8) {
            for ($i = 0; $i < $count; $i++) {
                $raw[$i] = (float) round($raw[$i] / $norm, 6);
            }
        }

        return $raw;
    }

    /**
     * Proses identifikasi sidik jari di Kiosk Gerbang.
     *
     * Server-side memanggil bridge sensor (atau driver mock) — browser tidak
     * pernah memanggil bridge langsung (hindari CORS). Bila sensor tidak
     * tersedia/ dikenali → pesan ramah, TIDAK pernah 500.
     */
    public function identificarSidikJari(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['nullable', 'string', 'max:400000'],
            // auto=1 dikirim loop otomatis kiosk saat bridge (dipasok sebagai number/string/bool)
            'auto' => ['nullable'],
        ]);

        // Mock + auto=1 SELALU "tidak dikenali" (anti spam absen otomatis).
        // Bridge memperlakukan auto=1 identik dengan pemanggilan biasa.
        $auto = filter_var($request->input('auto'), FILTER_VALIDATE_BOOLEAN);

        $fingerprint = FingerprintService::dariConfig();
        $hasil = $fingerprint->identifyDetailed($auto);

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
            return response()->json([
                'success' => false,
                'type' => 'fingerprint_unknown',
                'message' => 'Sidik jari tidak dikenali. Daftar dulu bila belum terdaftar.',
                'registration_url' => route('pendaftaran.index'),
            ], 200);
        }

        $student = Student::with(['schoolClass', 'rfidCard'])->where('fingerprint_id', $huellaId)->first();

        if (! $student) {
            return response()->json([
                'success' => false,
                'type' => 'fingerprint_unknown',
                'message' => 'Sidik jari tidak dikenali. Daftar dulu bila belum terdaftar.',
                'registration_url' => route('pendaftaran.index'),
            ], 200);
        }

        $response = $this->prosesAbsensi($student, $request->input('photo'), 'gerbang-fingerprint');
        $data = $response->getData(true);
        $data['method'] = 'Sidik Jari';
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

    /**
     * Logika absensi masuk/pulang yang sama dengan kiosk lama
     * (masuk/pulang berdasarkan catatan hari ini, cooldown 60 detik,
     * foto bukti opsional) — TIDAK DIUBAH.
     *
     * Kelas yang dipakai untuk batas Hadir/Terlambat adalah kelas SISWA,
     * bukan kelas perangkat (tidak ada lagi kiosk per kelas).
     */
    private function prosesAbsensi(Student $student, ?string $photo, string $device): JsonResponse
    {
        if (! $student->relationLoaded('schoolClass')) {
            $student->load('schoolClass');
        }

        $schoolClass = $student->schoolClass;

        // Waktu Server Mutlak (PRD R7 & Konstitusi II)
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        $attendance = Attendance::where('student_id', $student->id)
            ->where('date', $today)
            ->first();

        // Anti-Double-Tap Cooldown (PRD R3): selang < 60 detik diabaikan.
        if ($attendance && $attendance->last_tap_at && abs($now->diffInSeconds($attendance->last_tap_at)) < 60) {
            return response()->json([
                'success' => false,
                'type' => 'cooldown',
                'student_name' => $student->name,
                'message' => 'Kartu baru saja dicatat. Silakan tunggu sebentar.',
            ], 429);
        }

        // Alur Tap Pertama = Absen Masuk
        if (! $attendance) {
            // Status: <= entry_time = HADIR, > entry_time = TERLAMBAT
            $status = ($currentTime <= $schoolClass->entry_time) ? 'HADIR' : 'TERLAMBAT';

            $attendance = Attendance::create([
                'student_id' => $student->id,
                'school_class_id' => $schoolClass->id,
                'date' => $today,
                'check_in_at' => $currentTime,
                'status' => $status,
                'last_tap_at' => $now,
                'kiosk_device' => $device,
                'photo_in_path' => $this->simpanFotoBukti($photo, $student, 'masuk', $now),
            ]);

            $statusLabel = ($status === 'HADIR') ? 'Hadir Tepat Waktu' : 'Terlambat';

            return response()->json([
                'success' => true,
                'type' => 'check_in',
                'student_name' => $student->name,
                'nis' => $student->nis,
                'kelas' => $schoolClass->name,
                'status' => $status,
                'status_label' => $statusLabel,
                'time' => $currentTime,
                'message' => "Selamat datang, {$student->name}!",
            ]);
        }

        // Alur Tap Kedua = Absen Pulang
        if (! $attendance->check_out_at) {
            $attendance->update([
                'check_out_at' => $currentTime,
                'last_tap_at' => $now,
                'photo_out_path' => $this->simpanFotoBukti($photo, $student, 'pulang', $now) ?? $attendance->photo_out_path,
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

        // Sudah tap masuk & pulang
        return response()->json([
            'success' => false,
            'type' => 'already_completed',
            'student_name' => $student->name,
            'kelas' => $schoolClass->name,
            'message' => "Siswa {$student->name} sudah menyelesaikan absen masuk dan pulang hari ini.",
        ], 200);
    }

    /**
     * Simpan foto bukti absen (PRD F1) dari kamera kiosk.
     *
     * Input berupa data URL JPEG base64. Mengembalikan path relatif pada disk
     * 'local' (private), atau null bila tidak ada/tidak valid. Kegagalan foto
     * TIDAK BOLEH menggagalkan proses tap — foto bersifat pelengkap bukti.
     */
    private function simpanFotoBukti(?string $photoBase64, Student $student, string $jenis, Carbon $now): ?string
    {
        if (! $photoBase64 || ! preg_match('#^data:image/jpeg;base64,#', $photoBase64)) {
            return null;
        }

        try {
            $binary = base64_decode(substr($photoBase64, strpos($photoBase64, ',') + 1), true);

            // Batas ukuran wajar (~ 500 KB) sebagai pengaman tambahan
            if ($binary === false || strlen($binary) < 100 || strlen($binary) > 500 * 1024) {
                return null;
            }

            $path = sprintf(
                'attendance-photos/%s/%d-%s-%s.jpg',
                $now->toDateString(),
                $student->id,
                $jenis,
                $now->format('His')
            );

            if (! Storage::disk('local')->put($path, $binary)) {
                return null;
            }

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
