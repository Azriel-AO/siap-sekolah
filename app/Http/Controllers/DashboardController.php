<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\RekapPeriodeHelper;
use App\Services\RekapRingkasanPeriode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard view with authenticated user info.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Label peran ramah pengguna
        $roleLabels = [
            'admin' => 'Admin',
            'guru' => 'Guru',
            'siswa' => 'Siswa',
        ];

        $roleLabel = $roleLabels[$user->role] ?? ucfirst($user->role);

        // Banner pengingat: siswa yang belum punya baris face_enrollments
        // ditampilkan di dashboard (relasi & fallback demo sama seperti controllers siswa lain)
        $siswaPerluDaftarWajah = false;
        if ($user->role === 'siswa') {
            $student = Student::where('user_id', $user->id)->first() ?? Student::first();
            $siswaPerluDaftarWajah = ! $student || ! $student->faceEnrollment;
        }

        // Data Ringkasan 7 Hari Terakhir & Minggu Ini (re-use helper rekap periode)
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $sevenDaysAgo = $today->copy()->subDays(6);

        $dailyHadirCounts = Attendance::whereBetween('date', [$sevenDaysAgo->toDateString(), $today->toDateString()])
            ->where('status', 'HADIR')
            ->selectRaw('date, count(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->mapWithKeys(fn ($total, $date) => [substr((string) $date, 0, 10) => (int) $total]);

        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $chart7Hari = [];
        $maxHadir7Hari = 0;

        for ($i = 0; $i < 7; $i++) {
            $date = $sevenDaysAgo->copy()->addDays($i);
            $dateStr = $date->toDateString();
            $hadir = (int) ($dailyHadirCounts[$dateStr] ?? 0);
            if ($hadir > $maxHadir7Hari) {
                $maxHadir7Hari = $hadir;
            }
            $chart7Hari[] = [
                'date' => $dateStr,
                'day_label' => $dayNames[$date->dayOfWeek],
                'date_label' => $date->format('d/m'),
                'hadir' => $hadir,
            ];
        }

        $scaleMax = max(1, $maxHadir7Hari);
        foreach ($chart7Hari as &$item) {
            $item['height_percent'] = $item['hadir'] > 0
                ? max(12, (int) round(($item['hadir'] / $scaleMax) * 100))
                : 0;
        }
        unset($item);

        $rentangMinggu = RekapPeriodeHelper::hitungRentang('minggu', $today->toDateString());
        $students = Student::whereIn('school_class_id', SchoolClass::pluck('id'))->get();
        $ringkasanMinggu = RekapRingkasanPeriode::hitungPerSiswa(
            $students,
            $rentangMinggu['start_date'],
            $rentangMinggu['end_date'],
            $rentangMinggu['hari_sekolah']
        );

        $weeklyTotals = [
            'hadir' => 0,
            'terlambat' => 0,
            'izin_sakit' => 0,
            'alpa' => 0,
        ];

        foreach ($ringkasanMinggu as $row) {
            $weeklyTotals['hadir'] += $row['hadir'];
            $weeklyTotals['terlambat'] += $row['terlambat'];
            $weeklyTotals['izin_sakit'] += $row['izin'] + $row['sakit'];
            $weeklyTotals['alpa'] += $row['alpa'];
        }

        return view('dashboard', [
            'user' => $user,
            'roleLabel' => $roleLabel,
            'siswaPerluDaftarWajah' => $siswaPerluDaftarWajah,
            'chart7Hari' => $chart7Hari,
            'weeklyTotals' => $weeklyTotals,
        ]);
    }
}
