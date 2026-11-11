<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Services\RekapPeriodeHelper;
use App\Services\RekapRingkasanPeriode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRekapitulasiController extends Controller
{
    /**
     * Halaman admin "Rekapitulasi": bar chart per kelas.
     */
    public function index(Request $request): View
    {
        $periode = RekapPeriodeHelper::normalisasiPeriode($request->query('periode'));
        $tanggal = $this->tanggalDipilih($request);
        $rentang = RekapPeriodeHelper::hitungRentang($periode, $tanggal);

        return view('admin.rekapitulasi', [
            'periode' => $periode,
            'periodeLabel' => $rentang['label_periode'],
            'rentangLabel' => $rentang['label_rentang'],
            'tanggal' => $rentang['tanggal'],
            'tanggalDisplay' => $periode === 'hari'
                ? Carbon::parse($rentang['tanggal'])->translatedFormat('l, d F Y')
                : $rentang['label_rentang'],
        ]);
    }

    /**
     * Endpoint JSON grafik: per kelas, hitung Hadir / Terlambat / Tidak Hadir
     * dari catatan kehadiran dalam rentang periode yang dipilih.
     *
     * Definisi (mengikuti logika yang sudah ada):
     * - Hadir/Terlambat = status yang tercatat di attendances dalam rentang.
     * - Tidak Hadir = total siswa kelas x hari sekolah (Senin–Jumat) − Hadir − Terlambat.
     */
    public function grafik(Request $request): JsonResponse
    {
        $periode = RekapPeriodeHelper::normalisasiPeriode($request->query('periode'));
        $tanggal = $this->tanggalDipilih($request);
        $rentang = RekapPeriodeHelper::hitungRentang($periode, $tanggal);

        $classes = SchoolClass::withCount('students')->orderBy('name')->get();
        $students = \App\Models\Student::whereIn('school_class_id', $classes->pluck('id'))->get();
        $perSiswa = RekapRingkasanPeriode::hitungPerSiswa(
            $students,
            $rentang['start_date'],
            $rentang['end_date'],
            $rentang['hari_sekolah']
        );

        $labels = [];
        $hadir = [];
        $terlambat = [];
        $tidakHadir = [];

        foreach ($classes as $kelas) {
            $kelasSiswaIds = $students->where('school_class_id', $kelas->id)->pluck('id');
            $h = 0;
            $t = 0;
            foreach ($kelasSiswaIds as $id) {
                $h += $perSiswa[$id]['hadir'];
                $t += $perSiswa[$id]['terlambat'];
            }

            $labels[] = $kelas->name;
            $hadir[] = $h;
            $terlambat[] = $t;
            $tidakHadir[] = max(0, $kelas->students_count * $rentang['hari_sekolah'] - $h - $t);
        }

        return response()->json([
            'tanggal' => $rentang['tanggal'],
            'periode' => $periode,
            'periode_label' => $rentang['label_periode'],
            'rentang_label' => $rentang['label_rentang'],
            'hari_sekolah' => $rentang['hari_sekolah'],
            'labels' => $labels,
            'hadir' => $hadir,
            'terlambat' => $terlambat,
            'tidak_hadir' => $tidakHadir,
        ]);
    }

    private function tanggalDipilih(Request $request): string
    {
        $t = (string) $request->query('tanggal', '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && strtotime($t) !== false) {
            return $t;
        }

        return Carbon::now('Asia/Jakarta')->toDateString();
    }
}
