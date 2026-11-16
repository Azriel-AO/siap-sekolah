<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

class RekapPeriodeHelper
{
    /**
     * Normalisasi nilai periode: 'hari' | 'minggu' | 'bulan'. Default 'hari'.
     */
    public static function normalisasiPeriode(?string $periode): string
    {
        $p = strtolower(trim((string) $periode));

        return match ($p) {
            'minggu', 'minggu-ini', 'minggu_ini', 'mingguan', 'week', 'this_week' => 'minggu',
            'bulan', 'bulan-ini', 'bulan_ini', 'bulanan', 'month', 'this_month' => 'bulan',
            default => 'hari',
        };
    }

    /**
     * Ambil periode dari request ('periode' atau 'filter').
     */
    public static function ambilPeriodeDariRequest(Request $request): string
    {
        $raw = $request->input('periode') ?? $request->input('filter');

        return self::normalisasiPeriode(is_string($raw) ? $raw : null);
    }

    /**
     * Normalisasi string tanggal manual ('YYYY-MM-DD', 'DD/MM/YYYY', atau 'DD-MM-YYYY') ke 'YYYY-MM-DD'.
     * Mengembalikan null bila kosong atau tidak valid.
     */
    public static function normalisasiTanggal(?string $tanggal): ?string
    {
        $t = trim((string) $tanggal);
        if ($t === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if ($year >= 1970 && $year <= 2100 && checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            return null;
        }

        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $t, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($year >= 1970 && $year <= 2100 && checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            return null;
        }

        return null;
    }

    /**
     * Ambil tanggal dari request dengan memprioritaskan input manual ('date', 'tanggal', 'filter_date', 'start_date').
     * Hanya jatuh ke hari ini bila tidak ada input manual yang valid.
     */
    public static function ambilTanggalDariRequest(Request $request): string
    {
        foreach (['date', 'tanggal', 'filter_date', 'start_date'] as $key) {
            if ($request->filled($key)) {
                $val = $request->input($key);
                if (is_string($val)) {
                    $normalized = self::normalisasiTanggal($val);
                    if ($normalized !== null) {
                        return $normalized;
                    }
                }
            }
        }

        return Carbon::now('Asia/Jakarta')->toDateString();
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
        $tanggal = self::normalisasiTanggal($tanggal) ?? Carbon::now('Asia/Jakarta')->toDateString();

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
            : $start->translatedFormat('d F Y').' s/d '.$end->translatedFormat('d F Y');

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
        if ($end->lt($start)) {
            [$start, $end] = [$end->copy(), $start->copy()];
        }

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
