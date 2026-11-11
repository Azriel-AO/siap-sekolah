<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Data rekap kehadiran untuk export Excel/PDF.
 * Satu sumber data yang sama untuk kedua format.
 *
 * - Periode "Hari"   : kolom detail Tanggal, NISN, Nama, Kelas, Masuk, Pulang, Status, Metode.
 * - Periode "Minggu" : kolom ringkasan NISN, Nama, Kelas, Hadir, Terlambat, Tidak Hadir, Persentase.
 * - Periode "Bulan"  : kolom ringkasan yang sama.
 */
class RekapExportData
{
    public const HEADER = ['Tanggal', 'NISN', 'Nama', 'Kelas', 'Masuk', 'Pulang', 'Status', 'Metode'];

    public const HEADER_RINGKASAN = ['NISN', 'Nama', 'Kelas', 'Hadir', 'Terlambat', 'Tidak Hadir', 'Persentase'];

    /**
     * @return array{sekolah: string, periode: string, periodeLabel: string, rentangLabel: string, kelasLabel: string, rows: array<int, array>}
     */
    public function build(string $tanggal, ?string $slugKelas = null, string $periode = 'hari'): array
    {
        $periode = RekapPeriodeHelper::normalisasiPeriode($periode);

        $classes = SchoolClass::query()
            ->when($slugKelas && $slugKelas !== 'semua', fn ($q) => $q->where('slug', $slugKelas))
            ->orderBy('name')
            ->get();

        $rentang = RekapPeriodeHelper::hitungRentang($periode, $tanggal);
        $carbon = Carbon::parse($rentang['tanggal'], 'Asia/Jakarta');

        $rows = $periode === 'hari' ? [self::HEADER] : [self::HEADER_RINGKASAN];

        foreach ($classes as $kelas) {
            if ($periode === 'hari') {
                $students = $kelas->students()
                    ->with(['attendances' => function ($query) use ($rentang) {
                        $query->where('date', $rentang['tanggal']);
                    }])
                    ->orderBy('name')
                    ->get();

                foreach ($students as $siswa) {
                    $att = $siswa->attendances->first();

                    $rows[] = [
                        $carbon->format('d/m/Y'),
                        $siswa->nisn ?? '-',
                        $siswa->name,
                        $kelas->name,
                        $att && $att->check_in_at ? substr($att->check_in_at, 0, 5) : '-',
                        $att && $att->check_out_at ? substr($att->check_out_at, 0, 5) : '-',
                        $this->statusLabel($att?->status),
                        $this->metodeLabel($att?->kiosk_device),
                    ];
                }
            } else {
                $students = $kelas->students()->orderBy('name')->get();
                $perSiswa = RekapRingkasanPeriode::hitungPerSiswa(
                    $students,
                    $rentang['start_date'],
                    $rentang['end_date'],
                    $rentang['hari_sekolah']
                );

                foreach ($students as $siswa) {
                    $r = $perSiswa[$siswa->id];

                    $rows[] = [
                        $siswa->nisn ?? '-',
                        $siswa->name,
                        $kelas->name,
                        $r['hadir'],
                        $r['terlambat'],
                        $r['tidak_hadir'],
                        $r['persentase'] . '%',
                    ];
                }
            }
        }

        $labelPeriode = $rentang['label_periode'];

        return [
            'sekolah' => 'SMK KARYA NUGRAHA (KNBI)',
            'periode' => $periode,
            'periodeLabel' => $rentang['label_periode'],
            'rentangLabel' => $rentang['label_rentang'],
            'rentangAwal' => $rentang['start_str'],
            'rentangAkhir' => $rentang['end_str'],
            'tanggal' => $rentang['tanggal'],
            'kelasLabel' => ($slugKelas === null || $slugKelas === 'semua') ? 'Semua Kelas' : ($classes->first()?->name ?? 'Semua Kelas'),
            'rows' => $rows,
        ];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'HADIR' => 'Hadir',
            'TERLAMBAT' => 'Terlambat',
            'ALPA' => 'Alpa',
            'IZIN' => 'Izin',
            'SAKIT' => 'Sakit',
            default => 'Belum Absen',
        };
    }

    /**
     * Metode absen diambil dari jejak perangkat (kiosk_device):
     * "gerbang-fingerprint" → Sidik Jari; kartu/kiosk lama → Kartu.
     */
    private function metodeLabel(?string $device): string
    {
        $device = strtolower((string) ($device ?? ''));

        if ($device === '') {
            return '-';
        }

        if (str_contains($device, 'fingerprint')) {
            return 'Sidik Jari';
        }

        if (str_contains($device, 'kartu') || str_starts_with($device, 'kiosk')) {
            return 'Kartu';
        }

        return 'Lainnya';
    }
}
