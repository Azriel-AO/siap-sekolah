<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Layanan agregasi metrik kesehatan sistem & perangkat untuk dashboard status admin.
 */
class SystemHealthService
{
    /**
     * Kumpulkan ringkasan status server, database, sensor, serta histori ketersediaan 72 jam.
     *
     * @return array<string, mixed>
     */
    public function collectHealthReport(): array
    {
        $driver = (string) config('fingerprint.driver', 'mock');
        $baseUrl = (string) config('fingerprint.base_url', 'http://127.0.0.1:8787');
        $statusSensor = FingerprintService::dariConfig()->status();

        $now = Carbon::now('Asia/Jakarta');
        $bridgeUp = (bool) ($statusSensor['conectado'] ?? false);

        $slots72 = $this->buildHourlySlots($now, $bridgeUp);
        $downCount = collect($slots72)->where('status', 'down')->count();
        $slots24 = array_slice($slots72, -24);
        $chartData = $this->buildSvgChartData($slots24);

        return [
            'driver' => $driver,
            'baseUrl' => $baseUrl,
            'statusSensor' => $statusSensor,
            'jumlahKelas' => SchoolClass::count(),
            'jumlahSiswa' => Student::count(),
            'rfidStatus' => 'standby',
            'rfidMode' => 'USB HID (Keyboard Wedge)',
            'uptime3d' => round(((72 - $downCount) / 72) * 100, 1),
            'availability3d' => round(collect($slots72)->avg('availability'), 1),
            'daysGrid' => [
                ['label' => '48–72 jam lalu', 'slots' => array_slice($slots72, 0, 24)],
                ['label' => '24–48 jam lalu', 'slots' => array_slice($slots72, 24, 24)],
                ['label' => '24 jam terakhir', 'slots' => array_slice($slots72, 48, 24)],
            ],
            'slots24' => $slots24,
            'chartPoints' => $chartData['chartPoints'],
            'polylinePoints' => $chartData['polylinePoints'],
            'areaPoints' => $chartData['areaPoints'],
        ];
    }

    /**
     * @return list<array{date_key: string, day_label: string, hour_label: string, status: string, availability: int, note: string}>
     */
    private function buildHourlySlots(Carbon $now, bool $bridgeUp): array
    {
        $currentHour = $now->copy()->startOfHour();
        $start72h = $currentHour->copy()->subHours(71);

        $hourlyActivity = Attendance::whereNotNull('last_tap_at')
            ->where('last_tap_at', '>=', $start72h)
            ->get(['last_tap_at'])
            ->groupBy(fn (Attendance $item): string => Carbon::parse($item->last_tap_at, 'Asia/Jakarta')->format('Y-m-d H'))
            ->map->count();

        $slots = [];
        for ($offset = 71; $offset >= 0; $offset--) {
            $slotTime = $currentHour->copy()->subHours($offset);
            $hourKey = $slotTime->format('Y-m-d H');
            $hasActivity = ($hourlyActivity[$hourKey] ?? 0) > 0;
            $isSchoolHour = $slotTime->isWeekday() && $slotTime->hour >= 6 && $slotTime->hour <= 17;

            if ($offset === 0) {
                $status = $bridgeUp ? 'up' : 'degraded';
                $availability = $bridgeUp ? 100 : 75;
                $note = $bridgeUp
                    ? 'Semua layanan operasional (RFID standby USB HID)'
                    : 'Sensor sidik jari terputus';
            } elseif (! $bridgeUp && $isSchoolHour && ! $hasActivity) {
                $status = 'degraded';
                $availability = 75;
                $note = 'Sensor sidik jari tidak aktif';
            } else {
                $status = 'up';
                $availability = 100;
                $note = $hasActivity ? 'Normal (aktivitas absensi tercatat)' : 'Server & layanan normal';
            }

            $slots[] = [
                'date_key' => $slotTime->toDateString(),
                'day_label' => $slotTime->translatedFormat('D, d M'),
                'hour_label' => $slotTime->format('H:00'),
                'status' => $status,
                'availability' => $availability,
                'note' => $note,
            ];
        }

        return $slots;
    }

    /**
     * @param  list<array<string, mixed>>  $slots24
     * @return array{chartPoints: list<array<string, mixed>>, polylinePoints: string, areaPoints: string}
     */
    private function buildSvgChartData(array $slots24): array
    {
        $svgWidth = 760;
        $svgHeight = 140;
        $padLeft = 40;
        $padRight = 14;
        $padY = 16;
        $plotW = $svgWidth - $padLeft - $padRight;
        $plotH = $svgHeight - ($padY * 2);
        $pointCount = max(1, count($slots24) - 1);

        $chartPoints = [];
        foreach ($slots24 as $idx => $slot) {
            $chartPoints[] = [
                'x' => round($padLeft + ($idx / $pointCount) * $plotW, 1),
                'y' => round($padY + ((100 - $slot['availability']) / 100) * $plotH, 1),
                'hour_label' => $slot['hour_label'],
                'availability' => $slot['availability'],
                'status' => $slot['status'],
                'note' => $slot['note'],
            ];
        }

        $polylinePoints = collect($chartPoints)
            ->map(fn (array $pt): string => "{$pt['x']},{$pt['y']}")
            ->implode(' ');
        $areaPoints = $polylinePoints !== ''
            ? "{$padLeft},".($svgHeight - $padY)." {$polylinePoints} ".($svgWidth - $padRight).','.($svgHeight - $padY)
            : '';

        return [
            'chartPoints' => $chartPoints,
            'polylinePoints' => $polylinePoints,
            'areaPoints' => $areaPoints,
        ];
    }
}
