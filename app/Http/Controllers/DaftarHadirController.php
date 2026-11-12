<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Services\DaftarHadirBulanan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
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
     * NISN & INDUK selalu teks (leading zeros aman). Style: kop center + border matrix +
     * abu-abu muda kolom Sabtu/Minggu + footer 4 baris rata kanan.
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
        $boldCenter = (new Style)->setFontBold()->setCellAlignment(CellAlignment::CENTER);
        $center = (new Style)->setCellAlignment(CellAlignment::CENTER);

        $borderThin = new Border(
            new BorderPart(Border::LEFT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::TOP, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
        );

        $thin = (new Style)->setBorder($borderThin);
        $boldThin = (new Style)->setFontBold()->setBorder($borderThin);
        $boldCenterThin = (new Style)->setFontBold()->setCellAlignment(CellAlignment::CENTER)->setBorder($borderThin);
        $grayThin = (new Style)->setBackgroundColor('E5E7EB')->setBorder($borderThin);
        $boldCenterGrayThin = (new Style)->setFontBold()->setCellAlignment(CellAlignment::CENTER)->setBackgroundColor('E5E7EB')->setBorder($borderThin);

        $symbolStyles = [
            '√' => (new Style)->setFontBold()->setFontColor('008000')->setCellAlignment(CellAlignment::CENTER)->setBorder($borderThin),
            'I' => (new Style)->setFontBold()->setFontColor('FFA500')->setCellAlignment(CellAlignment::CENTER)->setBorder($borderThin),
            'A' => (new Style)->setFontBold()->setFontColor('FF0000')->setCellAlignment(CellAlignment::CENTER)->setBorder($borderThin),
            'S' => (new Style)->setFontBold()->setFontColor('2563EB')->setCellAlignment(CellAlignment::CENTER)->setBorder($borderThin),
        ];

        $right = (new Style)->setCellAlignment(CellAlignment::RIGHT);
        $sheetView = (new SheetView)->setShowGridLines(false);

        $tmp = tempnam(sys_get_temp_dir(), 'daftar_hadir_').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($tmp);

        $first = true;
        foreach ($kelasExport as $kelas) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $sheet = $writer->getCurrentSheet();
            $sheet->setName(DaftarHadirBulanan::namaSheet($kelas->name));
            $sheet->setSheetView($sheetView);

            // Lebar kolom: NO=4, NISN=14, INDUK=12, NAMA=40, hari 1..31 = 4.5
            $sheet->setColumnWidth(4, 1);
            $sheet->setColumnWidth(14, 2);
            $sheet->setColumnWidth(12, 3);
            $sheet->setColumnWidth(40, 4);
            for ($d = 5; $d <= 35; $d++) {
                $sheet->setColumnWidth(4.5, $d);
            }

            $rows = $builder->buildRows($kelas, $bulan, $tahun, $pengaturan)['rows'];
            $hariDalamBulan = Carbon::create($tahun, $bulan, 1)->daysInMonth;

            foreach ($rows as $i => $row) {
                // Baris judul (0,1,2) ditebalkan & rata tengah di kolom 16 (indeks 15), tanpa border
                if ($i === 0 || $i === 1 || $i === 2) {
                    $kopRow = array_fill(0, DaftarHadirBulanan::JUMLAH_KOLOM, '');
                    $kopRow[15] = (string) ($row[0] ?? ($row[15] ?? ''));
                    $writer->addRow(Row::fromValues($kopRow, $boldCenter));
                } elseif ($i >= 3 && $i <= 5) {
                    // BULAN / KELAS / WALI KELAS: label kolom 18, nilai kolom 25 (area kanan), tanpa border
                    $writer->addRow(Row::fromValues($row, $bold));
                } elseif ($i === 6) {
                    $writer->addRow(Row::fromValues($row));
                } elseif ($i === 7) {
                    // Header kolom NO/NISN/INDUK/NAMA/TANGGAL ditebalkan + border tipis matrix
                    $headerStyles = [];
                    for ($c = 0; $c < DaftarHadirBulanan::JUMLAH_KOLOM; $c++) {
                        $headerStyles[$c] = $boldThin;
                    }
                    $headerRow = array_pad($row, DaftarHadirBulanan::JUMLAH_KOLOM, '');
                    $writer->addRow(Row::fromValuesWithStyles($headerRow, null, $headerStyles));
                } elseif ($i === 8) {
                    // Angka hari 1..31 + border tipis; Sabtu/Minggu abu-abu
                    $dayStyles = [];
                    for ($c = 0; $c <= 3; $c++) {
                        $dayStyles[$c] = $boldCenterThin;
                    }
                    for ($d = 1; $d <= DaftarHadirBulanan::JUMLAH_KOLOM_TANGGAL; $d++) {
                        // Shading hanya untuk tanggal nyata bulan ini; kolom 29-31 di luar bulan = kosong biasa
                        $weekend = $d <= $hariDalamBulan && Carbon::create($tahun, $bulan, $d)->isWeekend();
                        $dayStyles[4 + $d - 1] = $weekend ? $boldCenterGrayThin : $boldCenterThin;
                    }
                    $writer->addRow(Row::fromValuesWithStyles($row, null, $dayStyles));
                } elseif ($i >= 9 && $i < count($rows) - 5) {
                    // Baris siswa: border tipis + hari weekend abu-abu + warna simbol (√ hijau, I oranye, A merah, S biru)
                    $cellStyles = [];
                    for ($c = 0; $c <= 3; $c++) {
                        $cellStyles[$c] = $thin;
                    }
                    for ($d = 1; $d <= DaftarHadirBulanan::JUMLAH_KOLOM_TANGGAL; $d++) {
                        $colIdx = 4 + $d - 1;
                        $val = (string) ($row[$colIdx] ?? '');
                        $weekend = $d <= $hariDalamBulan && Carbon::create($tahun, $bulan, $d)->isWeekend();

                        if (isset($symbolStyles[$val])) {
                            $cellStyles[$colIdx] = $symbolStyles[$val];
                        } else {
                            $cellStyles[$colIdx] = $weekend ? $grayThin : $thin;
                        }
                    }
                    $writer->addRow(Row::fromValuesWithStyles($row, null, $cellStyles));
                } elseif ($i === count($rows) - 5) {
                    // Baris pemisah kosong sebelum footer: tanpa border
                    $writer->addRow(Row::fromValues($row));
                } else {
                    // Footer 4 baris: teks di kolom 25, rata kanan, tanpa border
                    $writer->addRow(Row::fromValues($row, $right));
                }
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
        $filename = 'DAFTAR-HADIR-'.$namaBulan.'-'.$tahun.'-'.str_replace(' ', '-', $slug).'.xlsx';

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
