<?php

namespace Tests\Feature;

use App\Console\Commands\ResetDemoProduksiCommand;
use App\Models\Attendance;
use App\Models\FaceEnrollment;
use App\Models\IzinSakit;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoAttendanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResetDemoProduksiCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reset_demo_menghapus_data_orang_dan_sinkron_kelas_asli(): void
    {
        Storage::fake('local');

        $this->seed([
            UserSeeder::class,
            DemoAttendanceSeeder::class,
        ]);

        Setting::set('nama_sekolah', 'SMK KARYA NUGRAHA BOYOLALI');

        $siswa = Student::firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();

        Storage::disk('local')->put("face-photos/{$siswa->id}/face-1.jpg", 'dummy');
        FaceEnrollment::create([
            'student_id' => $siswa->id,
            'photo_1_path' => "face-photos/{$siswa->id}/face-1.jpg",
            'photo_2_path' => "face-photos/{$siswa->id}/face-2.jpg",
            'photo_3_path' => "face-photos/{$siswa->id}/face-3.jpg",
            'consented_at' => now(),
        ]);

        IzinSakit::create([
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'status' => 'izin',
            'keterangan' => 'Urusan keluarga',
            'recorded_by' => $admin->id,
        ]);

        $this->artisan('produksi:reset-demo')
            ->assertExitCode(0);

        // Data orang demo bersih
        $this->assertSame(0, Student::count());
        $this->assertSame(0, Attendance::count());
        $this->assertSame(0, RfidCard::count());
        $this->assertSame(0, FaceEnrollment::count());
        $this->assertSame(0, IzinSakit::count());
        $this->assertEmpty(Storage::disk('local')->directories('face-photos'));

        // User non-admin terhapus, admin & settings tetap utuh
        $this->assertSame(0, User::where('role', '!=', 'admin')->count());
        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame('SMK KARYA NUGRAHA BOYOLALI', Setting::get('nama_sekolah'));

        // Kelas demo X-TKJ-1 hilang, diganti 33 kelas asli
        $this->assertDatabaseMissing('school_classes', ['slug' => 'X-TKJ-1']);

        $daftarEkspektasi = [
            // Kelas 10 (13 kelas)
            '10 TKR 1', '10 TKR 2', '10 TKR 3', '10 TKR 4', '10 TKR 5',
            '10 TBKR 1',
            '10 TSM 1', '10 TSM 2', '10 TSM 3',
            '10 TKJ 1', '10 TKJ 2', '10 TKJ 3',
            '10 PSY 1',
            // Kelas 11 (12 kelas)
            '11 TKR 1', '11 TKR 2', '11 TKR 3', '11 TKR 4', '11 TKR 5',
            '11 TBKR 1',
            '11 TSM 1', '11 TSM 2', '11 TSM 3',
            '11 TKJ 3',
            '11 TTT 1',
            '11 PS 1',
            // Kelas 12 (8 kelas)
            '12 TKR 5',
            '12 TBKR 1',
            '12 TSM 1', '12 TSM 2',
            '12 TKJ 1', '12 TKJ 2',
            '12 TTT 1',
            '12 PS 1',
        ];

        $this->assertCount(33, $daftarEkspektasi);
        $this->assertSame(33, SchoolClass::count());

        foreach ($daftarEkspektasi as $namaKelas) {
            $this->assertDatabaseHas('school_classes', [
                'name' => $namaKelas,
                'wali_kelas' => null,
                'entry_time' => '07:00:00',
                'exit_time' => '15:30:00',
            ]);
        }

        // Pastikan asumsi 11 TKJ hanya 3 dan 12 TKR hanya 5
        $this->assertDatabaseMissing('school_classes', ['name' => '11 TKJ 1']);
        $this->assertDatabaseMissing('school_classes', ['name' => '11 TKJ 2']);
        $this->assertDatabaseMissing('school_classes', ['name' => '12 TKR 1']);
        $this->assertDatabaseMissing('school_classes', ['name' => '12 TKR 4']);
        $this->assertCount(33, ResetDemoProduksiCommand::daftarKelasAsli());
    }
}
