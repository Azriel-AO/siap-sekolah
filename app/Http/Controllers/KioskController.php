<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\Student;
use App\Services\FingerprintService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $card = RfidCard::with(['student.schoolClass'])
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

        return $this->prosesAbsensi($card->student, $request->input('photo'), 'gerbang-kartu');
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
        $huellaId = $fingerprint->identify($auto);

        if ($huellaId === null) {
            return response()->json([
                'success' => false,
                'type' => 'fingerprint_unavailable',
                'message' => 'Sensor sidik jari tidak tersedia atau sidik jari tidak dikenali. Coba lagi atau gunakan kartu.',
            ], 503);
        }

        $student = Student::with('schoolClass')->where('fingerprint_id', $huellaId)->first();

        if (! $student) {
            return response()->json([
                'success' => false,
                'type' => 'fingerprint_not_found',
                'message' => 'Sidik jari tidak terdaftar. Silakan daftar dulu.',
                'registration_url' => route('pendaftaran.index'),
            ], 404);
        }

        return $this->prosesAbsensi($student, $request->input('photo'), 'gerbang-fingerprint');
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
