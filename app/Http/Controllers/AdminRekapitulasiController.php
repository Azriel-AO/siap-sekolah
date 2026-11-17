<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\RekapPeriodeHelper;
use App\Services\RekapRingkasanPeriode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminRekapitulasiController extends Controller
{
    /**
     * Halaman admin "Rekapitulasi": bar chart sesuai filter kelas & periode.
     */
    public function index(Request $request): View
    {
        $periode = RekapPeriodeHelper::ambilPeriodeDariRequest($request);
        $tanggal = $this->tanggalDipilih($request);
        $rentang = RekapPeriodeHelper::hitungRentang($periode, $tanggal);
        $classes = SchoolClass::orderBy('name')->get();
        $kelasDipilih = $this->kelasDipilih($request);

        return view('admin.rekapitulasi', [
            'classes' => $classes,
            'kelas' => $kelasDipilih?->slug ?? 'semua',
            'kelasLabel' => $kelasDipilih?->name ?? 'Semua Kelas',
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
     * Endpoint JSON grafik: hitung Hadir / Terlambat / Izin / Sakit / Alpa
     * dari catatan kehadiran dalam rentang periode dan filter kelas yang dipilih.
     *
     * - kelas=semua (default) -> 1 objek aggregasi total seluruh kelas.
     * - kelas=<slug>          -> 1 objek khusus kelas tersebut.
     */
    public function grafik(Request $request): JsonResponse
    {
        $periode = RekapPeriodeHelper::ambilPeriodeDariRequest($request);
        $rentang = RekapPeriodeHelper::hitungRentang($periode, $this->tanggalDipilih($request));
        $kelasDipilih = $this->kelasDipilih($request);

        $studentsQuery = Student::query();
        if ($kelasDipilih !== null) {
            $studentsQuery->where('school_class_id', $kelasDipilih->id);
        } else {
            $studentsQuery->whereIn('school_class_id', SchoolClass::pluck('id'));
        }

        $students = $studentsQuery->get();
        $perSiswa = RekapRingkasanPeriode::hitungPerSiswa(
            $students,
            $rentang['start_date'],
            $rentang['end_date'],
            $rentang['hari_sekolah']
        );

        $totals = $this->summarizePeriodTotals($students, $perSiswa);
        $label = $kelasDipilih ? $kelasDipilih->name : 'Semua Kelas';

        return response()->json([
            'tanggal' => $rentang['tanggal'],
            'periode' => $periode,
            'periode_label' => $rentang['label_periode'],
            'rentang_label' => $rentang['label_rentang'],
            'hari_sekolah' => $rentang['hari_sekolah'],
            'kelas' => $kelasDipilih?->slug ?? 'semua',
            'kelas_label' => $label,
            'labels' => [$label],
            'hadir' => [$totals['hadir']],
            'terlambat' => [$totals['terlambat']],
            'izin' => [$totals['izin']],
            'sakit' => [$totals['sakit']],
            'alpa' => [$totals['alpa']],
        ]);
    }

    /**
     * Akumulasikan jumlah Hadir, Terlambat, Izin, Sakit, dan Alpa untuk seluruh siswa terpilih.
     *
     * @param  Collection<int, Student>  $students
     * @param  array<int, array<string, mixed>>  $perSiswa
     * @return array{hadir: int, terlambat: int, izin: int, sakit: int, alpa: int}
     */
    private function summarizePeriodTotals(Collection $students, array $perSiswa): array
    {
        $totals = ['hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        foreach ($students as $siswa) {
            $ringkasan = $perSiswa[$siswa->id] ?? null;
            if (! $ringkasan) {
                continue;
            }
            $totals['hadir'] += $ringkasan['hadir'];
            $totals['terlambat'] += $ringkasan['terlambat'];
            $totals['izin'] += $ringkasan['izin'];
            $totals['sakit'] += $ringkasan['sakit'];
            $totals['alpa'] += $ringkasan['alpa'];
        }

        return $totals;
    }

    private function kelasDipilih(Request $request): ?SchoolClass
    {
        $slug = trim((string) $request->query('kelas', 'semua'));
        if ($slug === '' || $slug === 'semua') {
            return null;
        }

        return SchoolClass::where('slug', $slug)->first();
    }

    private function tanggalDipilih(Request $request): string
    {
        return RekapPeriodeHelper::ambilTanggalDariRequest($request);
    }
}
