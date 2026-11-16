<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\FaceEnrollment;
use App\Models\IzinSakit;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\SchoolClassSeeder;
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
        return SchoolClassSeeder::daftarKelasSmkKnbi();
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
