<?php

namespace App\Services;

use App\Models\SchoolClass;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Pembentuk file Excel "DAFTAR HADIR" bulanan per kelas dengan pemformatan resmi sekolah.
 */
class DaftarHadirExcelExporter
{
    /**
     * Hasilkan binary file `.xlsx` beserta nama unduhannya.
     *
     * @param  Collection<int, SchoolClass>  $kelasExport
     * @return array{content: string, filename: string}
     */
    public function export(Collection $kelasExport, int $bulan, int $tahun, string $slug): array
    {
        $styles = $this->buildStyles();
        $builder = new DaftarHadirBulanan;
        $pengaturan = DaftarHadirBulanan::pengaturan();

        $tmp = tempnam(sys_get_temp_dir(), 'daftar_hadir_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($tmp);

        $first = true;
        foreach ($kelasExport as $kelas) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $this->writeClassSheet($writer, $builder, $kelas, $bulan, $tahun, $pengaturan, $styles);
            $first = false;
        }

        if ($kelasExport->isEmpty()) {
            $writer->getCurrentSheet()->setName('Kosong');
            $writer->addRow(Row::fromValues(['Tidak ada kelas.']));
        }

        $writer->close();

        $content = (string) file_get_contents($tmp);
        unlink($tmp);

        $namaBulan = Carbon::create($tahun, $bulan, 1)->translatedFormat('F');
        $filename = 'DAFTAR-HADIR-'.$namaBulan.'-'.$tahun.'-'.str_replace(' ', '-', $slug).'.xlsx';

        return [
            'content' => $content,
            'filename' => $filename,
        ];
    }

    /**
     * Tulis satu worksheet daftar hadir untuk satu kelas.
     *
     * @param  array<string, mixed>  $pengaturan
     * @param  array<string, mixed>  $styles
     */
    private function writeClassSheet(
        Writer $writer,
        DaftarHadirBulanan $builder,
        SchoolClass $kelas,
        int $bulan,
        int $tahun,
        array $pengaturan,
        array $styles
    ): void {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(DaftarHadirBulanan::namaSheet($kelas->name));

        $sheet->setColumnWidth(4, 1);
        $sheet->setColumnWidth(14, 2);
        $sheet->setColumnWidth(12, 3);
        $sheet->setColumnWidth(40, 4);
        for ($d = 5; $d <= 35; $d++) {
            $sheet->setColumnWidth(4.5, $d);
        }

        $rows = $builder->buildRows($kelas, $bulan, $tahun, $pengaturan)['rows'];
        $hariDalamBulan = Carbon::create($tahun, $bulan, 1)->daysInMonth;
        $weekendMap = $this->buildWeekendMap($tahun, $bulan, $hariDalamBulan);
        $totalRows = count($rows);

        foreach ($rows as $i => $row) {
            if ($i <= 2) {
                $kopRow = array_fill(0, DaftarHadirBulanan::JUMLAH_KOLOM, '');
                $kopRow[DaftarHadirBulanan::KOLOM_KOP_TENGAH] = (string) ($row[DaftarHadirBulanan::KOLOM_KOP_TENGAH] ?? ($row[0] ?? ''));
                $writer->addRow(Row::fromValues($kopRow, $styles['boldCenter']));
            } elseif ($i >= 3 && $i <= 5) {
                $writer->addRow(Row::fromValues($row, $styles['bold']));
            } elseif ($i === 6 || $i === $totalRows - 5) {
                $writer->addRow(Row::fromValues($row));
            } elseif ($i === 7 || $i === 8) {
                $headerStyles = $this->buildHeaderRowStyles($weekendMap, $styles);
                $paddedRow = $i === 7 ? array_pad($row, DaftarHadirBulanan::JUMLAH_KOLOM, '') : $row;
                $writer->addRow(Row::fromValuesWithStyles($paddedRow, null, $headerStyles));
            } elseif ($i >= 9 && $i < $totalRows - 5) {
                $writer->addRow(Row::fromValuesWithStyles($row, null, $this->buildStudentRowStyles($row, $weekendMap, $styles)));
            } else {
                $writer->addRow(Row::fromValues($row, $styles['right']));
            }
        }
    }

    /**
     * @param  array<int, bool>  $weekendMap
     * @param  array<string, mixed>  $styles
     * @return array<int, Style>
     */
    private function buildHeaderRowStyles(array $weekendMap, array $styles): array
    {
        $rowStyles = [];
        for ($c = 0; $c <= 3; $c++) {
            $rowStyles[$c] = $styles['boldCenterThin'];
        }
        for ($d = 1; $d <= DaftarHadirBulanan::JUMLAH_KOLOM_TANGGAL; $d++) {
            $rowStyles[4 + $d - 1] = ! empty($weekendMap[$d]) ? $styles['boldCenterGrayThin'] : $styles['boldCenterThin'];
        }

        return $rowStyles;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, bool>  $weekendMap
     * @param  array<string, mixed>  $styles
     * @return array<int, Style>
     */
    private function buildStudentRowStyles(array $row, array $weekendMap, array $styles): array
    {
        $cellStyles = [
            0 => $styles['centerThin'],
            1 => $styles['textThin'],
            2 => $styles['textThin'],
            3 => $styles['thin'],
        ];

        for ($d = 1; $d <= DaftarHadirBulanan::JUMLAH_KOLOM_TANGGAL; $d++) {
            $colIdx = 4 + $d - 1;
            $val = (string) ($row[$colIdx] ?? '');
            $weekend = ! empty($weekendMap[$d]);

            if (isset($styles['symbols'][$val])) {
                $cellStyles[$colIdx] = $weekend ? $styles['symbolWeekends'][$val] : $styles['symbols'][$val];
            } else {
                $cellStyles[$colIdx] = $weekend ? $styles['grayThin'] : $styles['thin'];
            }
        }

        return $cellStyles;
    }

    /**
     * @return array<int, bool>
     */
    private function buildWeekendMap(int $tahun, int $bulan, int $hariDalamBulan): array
    {
        $map = [];
        for ($d = 1; $d <= DaftarHadirBulanan::JUMLAH_KOLOM_TANGGAL; $d++) {
            $map[$d] = $d <= $hariDalamBulan && Carbon::create($tahun, $bulan, $d)->isWeekend();
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStyles(): array
    {
        $borderThin = new Border(
            new BorderPart(Border::LEFT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::TOP, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
        );

        $symbolColors = [
            '√' => '008000',
            'I' => 'FFA500',
            'A' => 'FF0000',
            'S' => '2563EB',
        ];

        $symbols = [];
        $symbolWeekends = [];
        foreach ($symbolColors as $sym => $hex) {
            $symbols[$sym] = (new Style)
                ->setFontBold()
                ->setFontColor($hex)
                ->setCellAlignment(CellAlignment::CENTER)
                ->setBorder($borderThin);
            $symbolWeekends[$sym] = (new Style)
                ->setFontBold()
                ->setFontColor($hex)
                ->setCellAlignment(CellAlignment::CENTER)
                ->setBackgroundColor('E5E7EB')
                ->setBorder($borderThin);
        }

        return [
            'bold' => (new Style)->setFontBold(),
            'boldCenter' => (new Style)->setFontBold()->setCellAlignment(CellAlignment::CENTER),
            'right' => (new Style)->setCellAlignment(CellAlignment::RIGHT),
            'thin' => (new Style)->setBorder($borderThin),
            'centerThin' => (new Style)->setCellAlignment(CellAlignment::CENTER)->setBorder($borderThin),
            'textThin' => (new Style)->setFormat('@')->setBorder($borderThin),
            'boldCenterThin' => (new Style)->setFontBold()->setCellAlignment(CellAlignment::CENTER)->setBorder($borderThin),
            'boldCenterGrayThin' => (new Style)->setFontBold()->setCellAlignment(CellAlignment::CENTER)->setBackgroundColor('E5E7EB')->setBorder($borderThin),
            'grayThin' => (new Style)->setBackgroundColor('E5E7EB')->setBorder($borderThin),
            'symbols' => $symbols,
            'symbolWeekends' => $symbolWeekends,
        ];
    }
}
