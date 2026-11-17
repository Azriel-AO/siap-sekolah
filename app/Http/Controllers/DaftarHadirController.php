<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Services\DaftarHadirBulanan;
use App\Services\DaftarHadirExcelExporter;
use App\Services\RekapPeriodeHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DaftarHadirController extends Controller
{
    public function __construct(private readonly DaftarHadirExcelExporter $exporter) {}

    /**
     * Halaman admin "Daftar Hadir": pilih bulan, tahun, kelas (semua/satu) dan pratinjau blok pertama.
     */
    public function index(Request $request): View
    {
        [$bulan, $tahun, $slug] = $this->resolveFilter($request);

        $kelas = SchoolClass::orderBy('name')->get();
        $kelasExport = (new DaftarHadirBulanan)->classesExport($slug);

        $preview = null;
        if ($kelasExport->count() === 1) {
            $preview = (new DaftarHadirBulanan)->buildRows($kelasExport->first(), $bulan, $tahun, DaftarHadirBulanan::pengaturan());
        }

        return view('admin.daftar-hadir.index', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'slug' => $slug,
            'classes' => $kelas,
            'kelasExport' => $kelasExport,
            'preview' => $preview,
            'pengaturan' => DaftarHadirBulanan::pengaturan(),
        ]);
    }

    /**
     * Unduh Excel "DAFTAR HADIR" — satu sheet per kelas.
     */
    public function excel(Request $request): Response
    {
        [$bulan, $tahun, $slug] = $this->resolveFilter($request);
        $kelasExport = (new DaftarHadirBulanan)->classesExport($slug);
        $exported = $this->exporter->export($kelasExport, $bulan, $tahun, $slug);

        return new Response($exported['content'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$exported['filename'].'"',
        ]);
    }

    /**
     * Ambil filter bulan, tahun, dan slug kelas dari request.
     *
     * @return array{0: int, 1: int, 2: string}
     */
    private function resolveFilter(Request $request): array
    {
        $tanggal = RekapPeriodeHelper::ambilTanggalDariRequest($request);
        $parsed = Carbon::parse($tanggal, 'Asia/Jakarta');
        $defaultBulan = (int) $parsed->month;
        $defaultTahun = (int) $parsed->year;

        $bulan = (int) filter_var($request->query('bulan', $defaultBulan), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?: $defaultBulan;
        $tahun = (int) filter_var($request->query('tahun', $defaultTahun), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) ?: $defaultTahun;
        $slug = (string) $request->query('kelas', 'semua');

        return [$bulan, $tahun, $slug];
    }
}
