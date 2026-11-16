<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\IzinSakit;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPendaftaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_admin_menampilkan_tombol_terminal_pendaftaran_di_tab_baru(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@knbi.sch.id',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Pendaftaran Siswa Baru');
        $response->assertSee('Buka Terminal Pendaftaran');

        $html = (string) $response->getContent();
        $pattern = '/<a\b[^>]*href="'.preg_quote(route('pendaftaran.index'), '/').'"[^>]*>.*?Buka Terminal Pendaftaran.*?<\/a>/s';
        preg_match($pattern, $html, $match);

        $this->assertNotEmpty($match, 'Link ke terminal pendaftaran tidak ditemukan di dashboard admin.');
        $this->assertStringContainsString('target="_blank"', $match[0]);
        $this->assertStringContainsString('rel="noopener"', $match[0]);
    }

    public function test_dashboard_menampilkan_ringkasan_7_hari_terakhir_dan_link_rekapitulasi(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@knbi.sch.id')->firstOrFail();
        $kelas = SchoolClass::firstOrFail();
        $today = Carbon::now('Asia/Jakarta');

        $siswa1 = Student::create([
            'school_class_id' => $kelas->id,
            'nis' => '880011',
            'nisn' => '0088001100',
            'name' => 'Siswa Grafik 1',
            'gender' => 'L',
        ]);
        $siswa2 = Student::create([
            'school_class_id' => $kelas->id,
            'nis' => '880012',
            'nisn' => '0088001200',
            'name' => 'Siswa Grafik 2',
            'gender' => 'P',
        ]);

        Attendance::create([
            'student_id' => $siswa1->id,
            'school_class_id' => $kelas->id,
            'date' => $today->toDateString(),
            'check_in_at' => '06:45:00',
            'status' => 'HADIR',
            'last_tap_at' => $today,
            'kiosk_device' => 'gerbang-kartu',
        ]);

        IzinSakit::create([
            'student_id' => $siswa2->id,
            'tanggal' => $today->toDateString(),
            'status' => 'izin',
            'keterangan' => 'Keperluan keluarga',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Ringkasan 7 Hari Terakhir');
        $response->assertSee('Total Hadir Minggu Ini');
        $response->assertSee('Total Terlambat');
        $response->assertSee('Total Izin/Sakit');
        $response->assertSee('Total Alpa');
        $response->assertSee('Lihat Detail →');
        $response->assertSee(route('admin.rekapitulasi'), false);
    }
}
