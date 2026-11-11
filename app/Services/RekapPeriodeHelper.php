<?php

namespace App\Services;

use Carbon\Carbon;

class RekapPeriodeHelper
{
    /**
     * Normalisasi nilai periode: 'hari' | 'minggu' | 'bulan'. Default 'hari'.
     */
    public static function normalisasiPeriode(?string $periode): string
    {
        $p = strtolower(trim((string) $periode));

        return match ($p) {
            'minggu', 'minggu-ini', 'mingguan' => 'minggu',
            'bulan', 'bulan-ini', 'bulanan' => 'bulan',
            default => 'hari',
        };
    }

    /**
     * Hitung rentang tanggal [periode, tanggal, start_date, end_date, start_str, end_str, hari_sekolah, label_periode, label_rentang]
     *
     * @return array{
     *     periode: string,
     *     tanggal: string,
     *     start_date: Carbon,
     *     end_date: Carbon,
     *     start_str: string,
     *     end_str: string,
     *     hari_sekolah: int,
     *     label_periode: string,
     *     label_rentang: string
     * }
     */
    public static function hitungRentang(string $periode, ?string $tanggal = null): array
    {
        $periode = self::normalisasiPeriode($periode);

        if (! $tanggal || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || strtotime($tanggal) === false) {
            $tanggal = Carbon::now('Asia/Jakarta')->toDateString();
        }

        $base = Carbon::parse($tanggal, 'Asia/Jakarta');

        if ($periode === 'minggu') {
            $start = $base->copy()->startOfWeek(Carbon::MONDAY);
            $end = $base->copy()->endOfWeek(Carbon::SUNDAY);
            $labelPeriode = 'Minggu Ini';
        } elseif ($periode === 'bulan') {
            $start = $base->copy()->startOfMonth();
            $end = $base->copy()->endOfMonth();
            $labelPeriode = 'Bulan Ini';
        } else {
            $start = $base->copy()->startOfDay();
            $end = $base->copy()->endOfDay();
            $labelPeriode = 'Hari Ini';
        }

        $hariSekolah = self::hitungHariSekolah($start, $end);

        $labelRentang = $periode === 'hari'
            ? $base->translatedFormat('l, d F Y')
            : $start->translatedFormat('d F Y') . ' s/d ' . $end->translatedFormat('d F Y');

        return [
            'periode' => $periode,
            'tanggal' => $tanggal,
            'start_date' => $start,
            'end_date' => $end,
            'start_str' => $start->toDateString(),
            'end_str' => $end->toDateString(),
            'hari_sekolah' => $hariSekolah,
            'label_periode' => $labelPeriode,
            'label_rentang' => $labelRentang,
        ];
    }

    /**
     * Hitung hari sekolah = Senin sampai Jumat (Sabtu dan Minggu tidak dihitung).
     */
    public static function hitungHariSekolah(Carbon $start, Carbon $end): int
    {
        $count = 0;
        $curr = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($curr->lte($endDay)) {
            if ($curr->isWeekday()) {
                $count++;
            }
            $curr->addDay();
        }

        return max(1, $count);
    }
}
