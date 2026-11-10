<?php

namespace App\Http\Controllers;

use App\Services\RekapExportData;
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
        [$tanggal, $slugKelas] = $this->filter($request);

        $data = (new RekapExportData)->build($tanggal, $slugKelas);

        // Judul di atas tabel: nama sekolah + periode/kelas
        $rows = [
            [$data['sekolah']],
            ['Rekap Kehadiran — ' . $data['periode'] . ' — ' . $data['kelasLabel']],
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

        $filename = 'rekap-' . $tanggal . '-' . ($slugKelas ?? 'semua') . '.xlsx';

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export rekap ke PDF (dompdf). Hanya admin (route + middleware).
     */
    public function pdf(Request $request): Response
    {
        [$tanggal, $slugKelas] = $this->filter($request);

        $data = (new RekapExportData)->build($tanggal, $slugKelas);

        $pdf = Pdf::loadView('admin.rekap-export-pdf', $data)
            ->setPaper('a4', 'landscape');

        $filename = 'rekap-' . $tanggal . '-' . ($slugKelas ?? 'semua') . '.pdf';

        return new Response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Filter tanggal (default hari ini, format Y-m-d) + kelas (slug | semua).
     */
    private function filter(Request $request): array
    {
        $t = (string) $request->query('tanggal', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) || strtotime($t) === false) {
            $t = \Carbon\Carbon::now('Asia/Jakarta')->toDateString();
        }

        $slug = $request->query('kelas');
        $slug = ($slug === null || $slug === '' ) ? 'semua' : $slug;

        return [$t, $slug];
    }
}
