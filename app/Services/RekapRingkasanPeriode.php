<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\IzinSakit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ringkasan kehadiran per siswa untuk rentang hari sekolah (Senin–Jumat).
 * Dipakai rekap admin (mode periode Minggu/Bulan), export, dan grafik.
 *
 * Definisi:
 * - Hadir     = catatan kehadiran status HADIR dalam rentang.
 * - Terlambat = catatan kehadiran status TERLAMBAT dalam rentang.
 * - Izin      = catatan di tabel izin_sakit berstatus 'izin' dalam rentang.
 * - Sakit     = catatan di tabel izin_sakit berstatus 'sakit' dalam rentang.
 * - Alpa      = hari sekolah (Senin–Jumat) yang SUDAH LEWAT (tanggal < hari ini)
 *               dalam rentang, dikurangi Hadir+Terlambat+Izin+Sakit. TIDAK disimpan.
 * - Persentase = (Hadir + Terlambat) / (hari sekolah dalam rentang − Izin − Sakit) × 100.
 *               Pembagi ≤ 0 → null ("—") — izin/sakit tidak menghukum persentase; alpa menghukum.
 */
class RekapRingkasanPeriode
{
    /**
     * @param  Collection<int, \App\Models\Student>  $students
     * @return array<int, array{hadir:int, terlambat:int, izin:int, sakit:int, alpa:int, persentase:?int}> di-key oleh student_id
     */
    public static function hitungPerSiswa(Collection $students, Carbon $start, Carbon $end, int $hariSekolah): array
    {
        $ids = $students->pluck('id')->all();

        $attendCache = Attendance::whereIn('student_id', $ids)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('student_id, status, count(*) as jumlah')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('jumlah', 'status'));

        $izinCache = IzinSakit::whereIn('student_id', $ids)
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('student_id, status, count(*) as jumlah')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('jumlah', 'status'));

        // Hari sekolah yang sudah LEWAT (tanggal < hari ini) — untuk ALPA.
        $hariIni = Carbon::now('Asia/Jakarta')->startOfDay();
        $batasLewat = $end->copy()->startOfDay()->min($hariIni->copy()->subDay());
        $hariSekolahLewat = self::hitungHariSekolahLewat($start, $batasLewat);

        $hasil = [];
        foreach ($students as $siswa) {
            $att = $attendCache[$siswa->id] ?? collect();
            $izinSakit = $izinCache[$siswa->id] ?? collect();

            $hadir = (int) ($att['HADIR'] ?? 0);
            $terlambat = (int) ($att['TERLAMBAT'] ?? 0);
            $izin = (int) ($izinSakit['izin'] ?? 0);
            $sakit = (int) ($izinSakit['sakit'] ?? 0);
            $alpa = max(0, $hariSekolahLewat - $hadir - $terlambat - $izin - $sakit);

            $pembagi = $hariSekolah - $izin - $sakit;
            $persentase = $pembagi > 0 ? (int) round(($hadir + $terlambat) / $pembagi * 100) : null;

            $hasil[$siswa->id] = [
                'hadir' => $hadir,
                'terlambat' => $terlambat,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'persentase' => $persentase,
            ];
        }

        return $hasil;
    }

    /**
     * Hitung hari sekolah (Senin–Jumat) dalam rentang [$start..$end] (inklusif).
     * Bila $end < $start → 0 (tidak ada hari lewat).
     */
    private static function hitungHariSekolahLewat(Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return 0;
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

        return $count;
    }
}
