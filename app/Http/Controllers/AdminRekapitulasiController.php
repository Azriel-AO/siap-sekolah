<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\RekapPeriodeHelper;
use App\Services\RekapRingkasanPeriode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRekapitulasiController extends Controller
{
    /**
     * Halaman admin "Rekapitulasi": bar chart sesuai filter kelas & periode.
     */
    public function index(Request $request): View
    {
        $periode = RekapPeriodeHelper::normalisasiPeriode($request->query('periode'));
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
        $periode = RekapPeriodeHelper::normalisasiPeriode($request->query('periode'));
        $tanggal = $this->tanggalDipilih($request);
        $rentang = RekapPeriodeHelper::hitungRentang($periode, $tanggal);
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

        $h = 0;
        $t = 0;
        $i = 0;
        $s = 0;
        $a = 0;

        foreach ($students as $siswa) {
            $ringkasan = $perSiswa[$siswa->id] ?? null;
            if (! $ringkasan) {
                continue;
            }
            $h += $ringkasan['hadir'];
            $t += $ringkasan['terlambat'];
            $i += $ringkasan['izin'];
            $s += $ringkasan['sakit'];
            $a += $ringkasan['alpa'];
        }

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
            'hadir' => [$h],
            'terlambat' => [$t],
            'izin' => [$i],
            'sakit' => [$s],
            'alpa' => [$a],
        ]);
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
        $t = (string) $request->query('tanggal', '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && strtotime($t) !== false) {
            return $t;
        }

        return Carbon::now('Asia/Jakarta')->toDateString();
    }
}
