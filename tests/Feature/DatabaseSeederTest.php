<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\IzinSakit;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DummyStudentSeeder;
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

    public function test_dummy_student_seeder_menghasilkan_siswa_realistis_merata_dan_idempotent(): void
    {
        $this->seed(DummyStudentSeeder::class);

        $studentCount = Student::count();
        $this->assertGreaterThanOrEqual(50, $studentCount);
        $this->assertLessThanOrEqual(100, $studentCount);
        $this->assertSame(39, SchoolClass::count());

        // Pastikan terdistribusi merata di seluruh 39 kelas (setiap kelas punya minimal 1 siswa)
        $classesWithStudents = SchoolClass::has('students')->count();
        $this->assertSame(39, $classesWithStudents);

        // Pastikan setiap siswa punya NISN 10 digit unik & akun user {NISN}@knbi.sch.id
        $sampleStudent = Student::firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{10}$/', (string) $sampleStudent->nisn);
        $this->assertDatabaseHas('users', [
            'email' => $sampleStudent->nisn.'@knbi.sch.id',
            'role' => 'siswa',
        ]);

        // Pastikan data absensi & izin 3 hari terakhir ter-generate (20-30 record)
        $totalRecords = Attendance::count() + IzinSakit::count();
        $this->assertGreaterThanOrEqual(20, $totalRecords);
        $this->assertLessThanOrEqual(30, $totalRecords);

        // Jalankan ke-2 kalinya -> idempotent (tidak menduplikasi siswa, user, maupun absensi)
        $this->seed(DummyStudentSeeder::class);
        $this->assertSame($studentCount, Student::count());
        $this->assertSame($totalRecords, Attendance::count() + IzinSakit::count());
        $this->assertDatabaseHas('users', ['email' => 'admin@knbi.sch.id', 'role' => 'admin']);
    }
}
