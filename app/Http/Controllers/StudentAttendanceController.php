<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentAttendanceController extends Controller
{
    /**
     * Tampilkan halaman riwayat absensi pribadi siswa.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Cari record siswa yang tertaut dengan akun login
        $student = Student::with(['schoolClass', 'rfidCard'])
            ->where('user_id', $user->id)
            ->first();

        // Jika login dengan role siswa tapi belum tertaut (atau fallback demo)
        if (! $student) {
            $student = Student::with(['schoolClass', 'rfidCard'])->first();
        }

        $attendances = Attendance::where('student_id', $student->id)
            ->orderBy('date', 'desc')
            ->get();

        return view('student.attendance-history', [
            'student' => $student,
            'attendances' => $attendances,
        ]);
    }
}
