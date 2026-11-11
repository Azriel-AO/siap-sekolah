<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Services\DaftarHadirBulanan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class DaftarHadirController extends Controller
{
    /**
     * Halaman admin "Daftar Hadir": pilih bulan, tahun, kelas (semua/satu).
     * Juga tampilkan pratinjau ringkas blok pertama.
     */
    public function index(Request $request): View
    {
        $bulan = (int) filter_var($request->query('bulan', (int) now()->month), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?: (int) now()->month;
        $tahun = (int) filter_var($request->query('tahun', (int) now()->year), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) ?: (int) now()->year;

        $slug = $request->query('kelas', 'semua');

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
     * Unduh Excel "DAFTAR HADIR" — satu sheet per kelas, setiap sheet berisi blok bulan.
     * NISN & INDUK selalu teks (leading zeros aman).
     */
    public function excel(Request $request): Response
    {
        $bulan = (int) filter_var($request->query('bulan'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?: (int) now()->month;
        $tahun = (int) filter_var($request->query('tahun'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) ?: (int) now()->year;
        $slug = $request->query('kelas', 'semua');

        $kelasExport = (new DaftarHadirBulanan)->classesExport($slug);

        $builder = new DaftarHadirBulanan;
        $pengaturan = DaftarHadirBulanan::pengaturan();

        $bold = (new Style)->setFontBold();

        $tmp = tempnam(sys_get_temp_dir(), 'daftar_hadir_') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($tmp);

        $first = true;
        foreach ($kelasExport as $kelas) {
            // Sheet pertama sudah dibuat otomatis saat openToFile
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $writer->getCurrentSheet()->setName(DaftarHadirBulanan::namaSheet($kelas->name));

            $rows = $builder->buildRows($kelas, $bulan, $tahun, $pengaturan)['rows'];

            foreach ($rows as $i => $row) {
                // Baris judul (0-2) + baris kelas (4) + wali kelas (5) + header hari (7-8) ditebalkan
                $style = (in_array($i, [0, 1, 2, 4, 5, 7, 8], true)) ? $bold : null;
                $writer->addRow(Row::fromValues($row, $style));
            }

            $first = false;
        }

        if ($kelasExport->isEmpty()) {
            $writer->getCurrentSheet()->setName('Kosong');
            $writer->addRow(Row::fromValues(['Tidak ada kelas.']));
        }

        $writer->close();

        $content = file_get_contents($tmp);
        unlink($tmp);

        $namaBulan = Carbon::create($tahun, $bulan, 1)->translatedFormat('F');
        $filename = 'DAFTAR-HADIR-' . $namaBulan . '-' . $tahun . '-' . str_replace(' ', '-', $slug) . '.xlsx';

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}

