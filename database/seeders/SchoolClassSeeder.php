<?php

namespace Database\Seeders;

use App\Console\Commands\ResetDemoProduksiCommand;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    /**
     * Seed 33 kelas asli produksi SMK Karya Nugraha Boyolali.
     */
    public function run(): void
    {
        $daftarKelas = ResetDemoProduksiCommand::daftarKelasAsli();

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
    }
}
