<?php

namespace App\Services;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ringkasan kehadiran per siswa untuk rentang hari sekolah (Senin–Jumat).
 * Dipakai rekap admin (mode periode Minggu/Bulan), export, dan grafik.
 *
 * Definisi:
 * - Hadir / Terlambat = jumlah catatan kehadiran berstatus tersebut dalam rentang.
 * - Tidak Hadir = hari sekolah (Senin–Jumat, dari RekapPeriodeHelper) − Hadir − Terlambat.
 * - Persentase kehadiran = (Hadir + Terlambat) / hari sekolah × 100 (dibulatkan).
 */
class RekapRingkasanPeriode
{
    /**
     * @param  Collection<int, \App\Models\Student>  $students
     * @return array<int, array{hadir:int, terlambat:int, tidak_hadir:int, persentase:int}> di-key oleh student_id
     */
    public static function hitungPerSiswa(Collection $students, Carbon $start, Carbon $end, int $hariSekolah): array
    {
        $ids = $students->pluck('id')->all();

        $counts = Attendance::whereIn('student_id', $ids)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('student_id, status, count(*) as jumlah')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('jumlah', 'status'));

        $hasil = [];
        foreach ($students as $siswa) {
            $byStatus = $counts[$siswa->id] ?? collect();
            $hadir = (int) ($byStatus['HADIR'] ?? 0);
            $terlambat = (int) ($byStatus['TERLAMBAT'] ?? 0);
            $tidakHadir = max(0, $hariSekolah - $hadir - $terlambat);
            $persentase = $hariSekolah > 0 ? (int) round(($hadir + $terlambat) / $hariSekolah * 100) : 0;

            $hasil[$siswa->id] = [
                'hadir' => $hadir,
                'terlambat' => $terlambat,
                'tidak_hadir' => $tidakHadir,
                'persentase' => $persentase,
            ];
        }

        return $hasil;
    }
}
