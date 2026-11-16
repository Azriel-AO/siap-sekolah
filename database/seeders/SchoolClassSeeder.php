<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    /**
     * Daftar 39 kelas awal SMK Karya Nugraha Boyolali (SMK KNBI).
     * Format nama: "{TINGKAT} {JURUSAN} {NOMOR}".
     *
     * @return list<array{name: string, slug: string, department: string}>
     */
    public static function daftarKelasSmkKnbi(): array
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
                ['jurusan' => 'TKJ', 'nomor' => [1, 2, 3], 'department' => 'Teknik Komputer & Jaringan'],
                ['jurusan' => 'TTT', 'nomor' => [1], 'department' => 'Teknik Tata Udara & Pendingin'],
                ['jurusan' => 'PSY', 'nomor' => [1], 'department' => 'Perbankan Syariah'],
            ],
            '12' => [
                ['jurusan' => 'TKR', 'nomor' => [1, 2, 3, 4, 5], 'department' => 'Teknik Kendaraan Ringan'],
                ['jurusan' => 'TBKR', 'nomor' => [1], 'department' => 'Teknik Bodi Kendaraan Ringan'],
                ['jurusan' => 'TSM', 'nomor' => [1, 2], 'department' => 'Teknik Sepeda Motor'],
                ['jurusan' => 'TKJ', 'nomor' => [1, 2], 'department' => 'Teknik Komputer & Jaringan'],
                ['jurusan' => 'TTT', 'nomor' => [1], 'department' => 'Teknik Tata Udara & Pendingin'],
                ['jurusan' => 'PSY', 'nomor' => [1], 'department' => 'Perbankan Syariah'],
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
     * Seed 39 kelas awal SMK KNBI secara idempotent via updateOrCreate.
     */
    public function run(): void
    {
        foreach (self::daftarKelasSmkKnbi() as $item) {
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
