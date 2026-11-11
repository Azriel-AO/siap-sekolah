<?php

namespace App\Http\Controllers;

use App\Services\RekapExportData;
use App\Services\RekapPeriodeHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class AdminRekapExportController extends Controller
{
    /**
     * Export rekap ke Excel (.xlsx, openspout). Hanya admin (route + middleware).
     */
    public function excel(Request $request): Response
    {
        [$tanggal, $slugKelas, $periode] = $this->filter($request);

        $data = (new RekapExportData)->build($tanggal, $slugKelas, $periode);

        // Judul di atas tabel: nama sekolah + periode/kelas
        $rows = [
            [$data['sekolah']],
            ['Rekap Kehadiran — ' . $data['periodeLabel'] . ' (' . $data['rentangLabel'] . ') — ' . $data['kelasLabel']],
            [''],
            ...$data['rows'],
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'rekap_') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($tmp);
        $writer->getCurrentSheet()->setName('Rekap');
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        $content = file_get_contents($tmp);
        unlink($tmp);

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $this->namaFile($data, 'xlsx') . '"',
        ]);
    }

    /**
     * Export rekap ke PDF (dompdf). Hanya admin (route + middleware).
     */
    public function pdf(Request $request): Response
    {
        [$tanggal, $slugKelas, $periode] = $this->filter($request);

        $data = (new RekapExportData)->build($tanggal, $slugKelas, $periode);

        $pdf = Pdf::loadView('admin.rekap-export-pdf', $data)
            ->setPaper('a4', 'landscape');

        return new Response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->namaFile($data, 'pdf') . '"',
        ]);
    }

    /**
     * Nama file: rekap-hari-YYYY-MM-DD-[kelas|semua].ext
     *            rekap-minggu|bulan-YYYY-MM-DD-sd-YYYY-MM-DD-[kelas|semua].ext
     */
    private function namaFile(array $data, string $ext): string
    {
        $kelas = str_replace(' ', '-', $data['kelasLabel']);

        if ($data['periode'] === 'hari') {
            return 'rekap-hari-' . $data['tanggal'] . '-' . $kelas . '.' . $ext;
        }

        return 'rekap-' . $data['periode'] . '-' . $data['rentangAwal'] . '-sd-' . $data['rentangAkhir'] . '-' . $kelas . '.' . $ext;
    }

    /**
     * Filter: tanggal (default hari ini), kelas (slug | semua), periode (hari|minggu|bulan).
     */
    private function filter(Request $request): array
    {
        $t = (string) $request->query('tanggal', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) || strtotime($t) === false) {
            $t = \Carbon\Carbon::now('Asia/Jakarta')->toDateString();
        }

        $slug = $request->query('kelas');
        $slug = ($slug === null || $slug === '' ) ? 'semua' : $slug;

        $periode = RekapPeriodeHelper::normalisasiPeriode($request->query('periode'));

        return [$t, $slug, $periode];
    }
}
