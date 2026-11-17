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

        // Cari record siswa yang tertaut ketat dengan akun login (hindari fallback IDOR ke siswa lain)
        $student = Student::with(['schoolClass', 'rfidCard'])
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);
                if ($user->student_id) {
                    $query->orWhere('id', $user->student_id);
                }
            })
            ->first();

        if (! $student && $user->role === 'admin') {
            $requestedStudentId = $request->integer('student_id');
            $student = $requestedStudentId > 0
                ? Student::with(['schoolClass', 'rfidCard'])->find($requestedStudentId)
                : Student::with(['schoolClass', 'rfidCard'])->first();
        }

        abort_if(! $student, 404, 'Data siswa belum tertaut dengan akun ini.');

        $attendances = Attendance::where('student_id', $student->id)
            ->orderBy('date', 'desc')
            ->get();

        return view('student.attendance-history', [
            'student' => $student,
            'attendances' => $attendances,
        ]);
    }
}
