<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FingerprintService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStatusSistemController extends Controller
{
    /**
     * Halaman GUI status kesehatan sistem (khusus Admin, read-only).
     */
    public function index(Request $request): View
    {
        $driver = (string) config('fingerprint.driver', 'mock');
        $baseUrl = (string) config('fingerprint.base_url', 'http://127.0.0.1:8787');
        $statusSensor = FingerprintService::dariConfig()->status();

        $now = Carbon::now('Asia/Jakarta');
        $lastCardTapRaw = Attendance::whereNotNull('last_tap_at')
            ->where(function ($query): void {
                $query->where('kiosk_device', 'gerbang-kartu')
                    ->orWhere('kiosk_device', 'like', 'kiosk-%');
            })
            ->max('last_tap_at');

        $lastCardTapAt = $lastCardTapRaw ? Carbon::parse($lastCardTapRaw, 'Asia/Jakarta') : null;
        $rfidAktif = $lastCardTapAt !== null && $lastCardTapAt->greaterThanOrEqualTo($now->copy()->subHours(24));

        $bridgeUp = (bool) ($statusSensor['conectado'] ?? false);
        $currentHour = $now->copy()->startOfHour();
        $start72h = $currentHour->copy()->subHours(71);

        $hourlyActivity = Attendance::whereNotNull('last_tap_at')
            ->where('last_tap_at', '>=', $start72h)
            ->get(['last_tap_at'])
            ->groupBy(fn (Attendance $item): string => Carbon::parse($item->last_tap_at, 'Asia/Jakarta')->format('Y-m-d H'))
            ->map->count();

        $slots72 = [];
        for ($offset = 71; $offset >= 0; $offset--) {
            $slotTime = $currentHour->copy()->subHours($offset);
            $hourKey = $slotTime->format('Y-m-d H');
            $hasActivity = ($hourlyActivity[$hourKey] ?? 0) > 0;
            $isSchoolHour = $slotTime->isWeekday() && $slotTime->hour >= 6 && $slotTime->hour <= 17;

            if ($offset === 0) {
                $issues = ($bridgeUp ? 0 : 1) + ($rfidAktif ? 0 : 1);
                $status = $issues === 0 ? 'up' : 'degraded';
                $availability = $issues === 0 ? 100 : ($issues === 1 ? 75 : 50);
                $note = $issues === 0
                    ? 'Semua device aktif'
                    : ($bridgeUp ? 'RFID belum terdeteksi' : ($rfidAktif ? 'Sensor sidik jari terputus' : 'Sensor & RFID belum aktif'));
            } elseif (! $bridgeUp && $isSchoolHour && ! $hasActivity) {
                $status = 'degraded';
                $availability = 75;
                $note = 'Sensor sidik jari tidak aktif';
            } else {
                $status = 'up';
                $availability = 100;
                $note = $hasActivity ? 'Normal (aktivitas absensi tercatat)' : 'Server & layanan normal';
            }

            $slots72[] = [
                'date_key' => $slotTime->toDateString(),
                'day_label' => $slotTime->translatedFormat('D, d M'),
                'hour_label' => $slotTime->format('H:00'),
                'status' => $status,
                'availability' => $availability,
                'note' => $note,
            ];
        }

        $downCount = collect($slots72)->where('status', 'down')->count();
        $uptime3d = round(((72 - $downCount) / 72) * 100, 1);
        $availability3d = round(collect($slots72)->avg('availability'), 1);
        $daysGrid = [
            [
                'label' => '48–72 jam lalu',
                'slots' => array_slice($slots72, 0, 24),
            ],
            [
                'label' => '24–48 jam lalu',
                'slots' => array_slice($slots72, 24, 24),
            ],
            [
                'label' => '24 jam terakhir',
                'slots' => array_slice($slots72, 48, 24),
            ],
        ];
        $slots24 = array_slice($slots72, -24);

        // Precompute SVG polyline coordinates (viewBox 760x140)
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
            $x = round($padLeft + ($idx / $pointCount) * $plotW, 1);
            $y = round($padY + ((100 - $slot['availability']) / 100) * $plotH, 1);
            $chartPoints[] = [
                'x' => $x,
                'y' => $y,
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

        return view('admin.status-sistem.index', [
            'driver' => $driver,
            'baseUrl' => $baseUrl,
            'statusSensor' => $statusSensor,
            'jumlahKelas' => SchoolClass::count(),
            'jumlahSiswa' => Student::count(),
            'rfidAktif' => $rfidAktif,
            'lastCardTapAt' => $lastCardTapAt,
            'uptime3d' => $uptime3d,
            'availability3d' => $availability3d,
            'daysGrid' => $daysGrid,
            'slots24' => $slots24,
            'chartPoints' => $chartPoints,
            'polylinePoints' => $polylinePoints,
            'areaPoints' => $areaPoints,
        ]);
    }
}
