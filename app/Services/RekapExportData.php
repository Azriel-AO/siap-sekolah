<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Data rekap kehadiran untuk export Excel/PDF.
 * Satu sumber data yang sama untuk kedua format — kolom persis:
 * Tanggal, NISN, Nama, Kelas, Masuk, Pulang, Status, Metode.
 */
class RekapExportData
{
    public const HEADER = ['Tanggal', 'NISN', 'Nama', 'Kelas', 'Masuk', 'Pulang', 'Status', 'Metode'];

    /**
     * @return array{sekolah: string, periode: string, kelasLabel: string, rows: array<int, array>}
     */
    public function build(string $tanggal, ?string $slugKelas = null): array
    {
        $classes = SchoolClass::query()
            ->when($slugKelas && $slugKelas !== 'semua', fn ($q) => $q->where('slug', $slugKelas))
            ->orderBy('name')
            ->get();

        $carbon = Carbon::parse($tanggal, 'Asia/Jakarta');

        $rows = [self::HEADER];

        foreach ($classes as $kelas) {
            $students = Student::with(['attendances' => function ($q) use ($tanggal) {
                $q->where('date', $tanggal);
            }])->where('school_class_id', $kelas->id)
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
        }

        return [
            'sekolah' => 'SMK KARYA NUGRAHA (KNBI)',
            'periode' => $carbon->translatedFormat('l, d F Y'),
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
