<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class KioskController extends Controller
{
    /**
     * Tampilkan layar Kiosk kelas.
     */
    public function show(string $slug): View
    {
        $schoolClass = SchoolClass::where('slug', $slug)->firstOrFail();

        return view('kiosk.show', [
            'schoolClass' => $schoolClass,
        ]);
    }

    /**
     * Proses pembacaan tap kartu RFID di Kiosk.
     */
    public function tap(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'card_uid' => ['required', 'string', 'max:50'],
            // Foto bukti absen (PRD F1) dari kamera kiosk: opsional —
            // kiosk tanpa kamera tetap boleh tap. Format: data URL JPEG base64.
            'photo' => ['nullable', 'string', 'max:400000'],
        ]);

        $schoolClass = SchoolClass::where('slug', $slug)->firstOrFail();
        $cardUid = trim($request->input('card_uid'));

        // 1. Cari kartu RFID aktif
        $card = RfidCard::with(['student.schoolClass'])
            ->where('card_uid', $cardUid)
            ->where('is_active', true)
            ->first();

        if (! $card || ! $card->student) {
            return response()->json([
                'success' => false,
                'type' => 'not_found',
                'message' => 'Kartu tidak dikenali. Silakan hubungi petugas tata usaha.',
            ], 404);
        }

        $student = $card->student;

        // 2. Validasi Kiosk Terikat Kelas (PRD R2 & Konstitusi VII)
        if ($student->school_class_id !== $schoolClass->id) {
            return response()->json([
                'success' => false,
                'type' => 'wrong_class',
                'student_name' => $student->name,
                'message' => "Siswa tidak terdaftar di kelas ini (Kiosk khusus {$schoolClass->name}).",
            ], 422);
        }

        // 3. Waktu Server Mutlak (PRD R7 & Konstitusi II)
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        // 4. Periksa catatan kehadiran hari ini
        $attendance = Attendance::where('student_id', $student->id)
            ->where('date', $today)
            ->first();

        // 5. Anti-Double-Tap Cooldown (PRD R3): selang < 60 detik diabaikan.
        // abs() dipakai karena diffInSeconds di Carbon 3 bersifat bertanda (bisa negatif).
        if ($attendance && $attendance->last_tap_at && abs($now->diffInSeconds($attendance->last_tap_at)) < 60) {
            return response()->json([
                'success' => false,
                'type' => 'cooldown',
                'student_name' => $student->name,
                'message' => "Kartu baru saja dicatat. Silakan tunggu sebentar.",
            ], 429);
        }

        // 6. Alur Tap Pertama = Absen Masuk
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
                'kiosk_device' => 'kiosk-'.$schoolClass->slug,
                'photo_in_path' => $this->simpanFotoBukti($request->input('photo'), $student, 'masuk', $now),
            ]);

            $statusLabel = ($status === 'HADIR') ? 'Hadir Tepat Waktu' : 'Terlambat';

            return response()->json([
                'success' => true,
                'type' => 'check_in',
                'student_name' => $student->name,
                'nis' => $student->nis,
                'status' => $status,
                'status_label' => $statusLabel,
                'time' => $currentTime,
                'message' => "Selamat datang, {$student->name}!",
            ]);
        }

        // 7. Alur Tap Kedua = Absen Pulang
        if (! $attendance->check_out_at) {
            $attendance->update([
                'check_out_at' => $currentTime,
                'last_tap_at' => $now,
                'photo_out_path' => $this->simpanFotoBukti($request->input('photo'), $student, 'pulang', $now) ?? $attendance->photo_out_path,
            ]);

            return response()->json([
                'success' => true,
                'type' => 'check_out',
                'student_name' => $student->name,
                'nis' => $student->nis,
                'status' => $attendance->status,
                'status_label' => 'Absen Pulang Berhasil',
                'time' => $currentTime,
                'message' => "Selamat pulang, {$student->name}!",
            ]);
        }

        // 8. Sudah tap masuk & pulang
        return response()->json([
            'success' => false,
            'type' => 'already_completed',
            'student_name' => $student->name,
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

            // Batas ukuran wajar (± 500 KB) sebagai pengaman tambahan
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
