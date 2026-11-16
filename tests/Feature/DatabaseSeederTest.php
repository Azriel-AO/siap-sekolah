<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_produksi_menghasilkan_1_admin_39_kelas_dan_0_siswa(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Assert 1 user admin (sesuai UserSeeder produksi)
        $this->assertSame(1, User::count());
        $admin = User::first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('admin@knbi.sch.id', $admin->email);

        // Assert 39 kelas produksi asli (sesuai SchoolClassSeeder)
        $this->assertSame(39, SchoolClass::count());

        // Assert 0 siswa demo
        $this->assertSame(0, Student::count());

        // Jalankan seed ke-2 kalinya -> tetap 39 kelas (idempotent, tanpa duplikasi)
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(39, SchoolClass::count());
    }
}
