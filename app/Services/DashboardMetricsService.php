<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Layanan perhitungan ringkasan grafik 7 hari dan statistik mingguan untuk beranda dashboard.
 */
class DashboardMetricsService
{
    /**
     * Susun data grafik kehadiran 7 hari terakhir dan total status minggu berjalan.
     *
     * @return array{chart7Hari: list<array<string, mixed>>, weeklyTotals: array<string, int>}
     */
    public function buildMetrics(): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        return [
            'chart7Hari' => $this->buildSevenDayChart($today),
            'weeklyTotals' => $this->buildWeeklyTotals($today),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSevenDayChart(Carbon $today): array
    {
        $sevenDaysAgo = $today->copy()->subDays(6);

        $dailyHadirCounts = Attendance::whereBetween('date', [$sevenDaysAgo->toDateString(), $today->toDateString()])
            ->where('status', 'HADIR')
            ->selectRaw('date, count(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->mapWithKeys(fn ($total, $date): array => [substr((string) $date, 0, 10) => (int) $total]);

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

        return $chart7Hari;
    }

    /**
     * @return array<string, int>
     */
    private function buildWeeklyTotals(Carbon $today): array
    {
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

        return $weeklyTotals;
    }
}
