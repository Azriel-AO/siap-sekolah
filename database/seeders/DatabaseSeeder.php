<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database for production.
     * Menghasilkan 1 User Admin default dan 33 Kelas asli SMK Karya Nugraha Boyolali, tanpa siswa demo.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SchoolClassSeeder::class,
        ]);
    }
}
