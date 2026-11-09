<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherAttendanceController extends Controller
{
    /**
     * Tampilkan halaman rekap absensi harian kelas binaan.
     */
    public function index(Request $request): View
    {
        $schoolClass = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $studentsData = $this->getAttendanceData($schoolClass, $today);

        return view('teacher.attendance-today', [
            'schoolClass' => $schoolClass,
            'today' => $today,
            'summary' => $studentsData['summary'],
            'students' => $studentsData['students'],
        ]);
    }

    /**
     * Endpoint data JSON untuk live polling auto-refresh di browser guru.
     */
    public function data(Request $request): JsonResponse
    {
        $schoolClass = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $data = $this->getAttendanceData($schoolClass, $today);

        return response()->json([
            'success' => true,
            'class_name' => $schoolClass->name,
            'date_formatted' => Carbon::now('Asia/Jakarta')->translatedFormat('l, d F Y'),
            'server_time' => Carbon::now('Asia/Jakarta')->format('H:i:s'),
            'summary' => $data['summary'],
            'students' => $data['students'],
        ]);
    }

    /**
     * Helper untuk mengambil dan memformat kehadiran seluruh siswa kelas.
     */
    protected function getAttendanceData(SchoolClass $schoolClass, string $today): array
    {
        $students = $schoolClass->students()
            ->with(['attendances' => function ($query) use ($today) {
                $query->where('date', $today);
            }, 'rfidCard'])
            ->orderBy('name')
            ->get();

        $hadirCount = 0;
        $terlambatCount = 0;
        $belumAbsenCount = 0;

        $formatted = $students->map(function ($student) {
            $att = $student->attendances->first();

            $status = $att ? $att->status : 'BELUM_ABSEN';
            $checkIn = $att && $att->check_in_at ? substr($att->check_in_at, 0, 5) : '-';
            $checkOut = $att && $att->check_out_at ? substr($att->check_out_at, 0, 5) : '-';

            return [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'gender' => $student->gender,
                'card_uid' => $student->rfidCard?->card_uid ?? '-',
                'status' => $status,
                'status_label' => match ($status) {
                    'HADIR' => 'Hadir',
                    'TERLAMBAT' => 'Terlambat',
                    'ALPA' => 'Alpa',
                    default => 'Belum Absen',
                },
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ];
        });

        foreach ($formatted as $row) {
            if ($row['status'] === 'HADIR') $hadirCount++;
            elseif ($row['status'] === 'TERLAMBAT') $terlambatCount++;
            else $belumAbsenCount++;
        }

        return [
            'summary' => [
                'total' => $students->count(),
                'hadir' => $hadirCount,
                'terlambat' => $terlambatCount,
                'belum_absen' => $belumAbsenCount,
            ],
            'students' => $formatted,
        ];
    }
}
