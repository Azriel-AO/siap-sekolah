<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // 5. Anti-Double-Tap Cooldown (PRD R3): selang < 60 detik diabaikan
        if ($attendance && $attendance->last_tap_at && $now->diffInSeconds($attendance->last_tap_at) < 60) {
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
}
