<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\FaceEnrollment;
use App\Models\IzinSakit;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetDemoProduksiCommand extends Command
{
    /**
     * Nama dan signature command artisan.
     *
     * @var string
     */
    protected $signature = 'produksi:reset-demo';

    /**
     * Deskripsi command artisan.
     *
     * @var string
     */
    protected $description = 'Reset data orang demo (siswa, absensi, kartu, wajah, izin, user non-admin) dan sinkronkan daftar kelas asli produksi.';

    /**
     * Daftar kelas asli produksi SMK Karya Nugraha Boyolali.
     * Format nama: "{tingkat} {jurusan} {n}"
     *
     * @return list<array{name: string, slug: string, department: string}>
     */
    public static function daftarKelasAsli(): array
    {
        $definisi = [
            '10' => [
                ['jurusan' => 'TKR', 'nomor' => [1, 2, 3, 4, 5], 'department' => 'Teknik Kendaraan Ringan'],
                ['jurusan' => 'TBKR', 'nomor' => [1], 'department' => 'Teknik Bodi Kendaraan Ringan'],
                ['jurusan' => 'TSM', 'nomor' => [1, 2, 3], 'department' => 'Teknik Sepeda Motor'],
                ['jurusan' => 'TKJ', 'nomor' => [1, 2, 3], 'department' => 'Teknik Komputer & Jaringan'],
                ['jurusan' => 'PSY', 'nomor' => [1], 'department' => 'Perbankan Syariah'],
            ],
            '11' => [
                ['jurusan' => 'TKR', 'nomor' => [1, 2, 3, 4, 5], 'department' => 'Teknik Kendaraan Ringan'],
                ['jurusan' => 'TBKR', 'nomor' => [1], 'department' => 'Teknik Bodi Kendaraan Ringan'],
                ['jurusan' => 'TSM', 'nomor' => [1, 2, 3], 'department' => 'Teknik Sepeda Motor'],
                ['jurusan' => 'TKJ', 'nomor' => [3], 'department' => 'Teknik Komputer & Jaringan'],
                ['jurusan' => 'TTT', 'nomor' => [1], 'department' => 'Teknik Tata Udara & Pendingin'],
                ['jurusan' => 'PS', 'nomor' => [1], 'department' => 'Perbankan Syariah'],
            ],
            '12' => [
                ['jurusan' => 'TKR', 'nomor' => [5], 'department' => 'Teknik Kendaraan Ringan'],
                ['jurusan' => 'TBKR', 'nomor' => [1], 'department' => 'Teknik Bodi Kendaraan Ringan'],
                ['jurusan' => 'TSM', 'nomor' => [1, 2], 'department' => 'Teknik Sepeda Motor'],
                ['jurusan' => 'TKJ', 'nomor' => [1, 2], 'department' => 'Teknik Komputer & Jaringan'],
                ['jurusan' => 'TTT', 'nomor' => [1], 'department' => 'Teknik Tata Udara & Pendingin'],
                ['jurusan' => 'PS', 'nomor' => [1], 'department' => 'Perbankan Syariah'],
            ],
        ];

        $hasil = [];
        foreach ($definisi as $tingkat => $kelompokList) {
            foreach ($kelompokList as $kelompok) {
                foreach ($kelompok['nomor'] as $n) {
                    $name = "{$tingkat} {$kelompok['jurusan']} {$n}";
                    $slug = "{$tingkat}-{$kelompok['jurusan']}-{$n}";
                    $hasil[] = [
                        'name' => $name,
                        'slug' => $slug,
                        'department' => $kelompok['department'],
                    ];
                }
            }
        }

        return $hasil;
    }

    /**
     * Jalankan command reset data demo & sinkronisasi kelas asli.
     */
    public function handle(): int
    {
        $daftarKelas = self::daftarKelasAsli();

        DB::transaction(function () use ($daftarKelas): void {
            // 1. Hapus data orang demo (urutan anak -> induk)
            Attendance::query()->delete();
            RfidCard::query()->delete();
            FaceEnrollment::query()->delete();
            IzinSakit::query()->delete();
            Student::query()->delete();

            // Hapus user non-admin (guru & siswa demo), pertahankan admin
            User::where('role', '!=', 'admin')->delete();

            // 2. Sinkronkan school_classes ke daftar kelas asli
            $slugAsli = array_column($daftarKelas, 'slug');

            SchoolClass::whereNotIn('slug', $slugAsli)->delete();

            foreach ($daftarKelas as $item) {
                SchoolClass::updateOrCreate(
                    ['slug' => $item['slug']],
                    [
                        'name' => $item['name'],
                        'department' => $item['department'],
                        'wali_kelas' => null,
                        'entry_time' => '07:00:00',
                        'exit_time' => '15:30:00',
                    ]
                );
            }
        });

        // 3. Bersihkan folder face-photos/* di private disk (local)
        $disk = Storage::disk('local');
        if ($disk->exists('face-photos')) {
            foreach ($disk->directories('face-photos') as $dir) {
                $disk->deleteDirectory($dir);
            }
            foreach ($disk->files('face-photos') as $file) {
                $disk->delete($file);
            }
        }

        $this->info('Reset data demo berhasil dijalankan.');
        $this->line('Siswa tersisa      : '.Student::count());
        $this->line('Kelas tersinkron   : '.SchoolClass::count());
        $this->line('Admin dipertahankan: '.User::where('role', 'admin')->count());

        return self::SUCCESS;
    }
}
