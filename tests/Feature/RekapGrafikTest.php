<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapGrafikTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        User::factory()->create(['name' => 'Admin KNBI', 'email' => 'admin@knbi.sch.id', 'role' => 'admin']);
        User::factory()->create(['name' => 'Bu Sari', 'email' => 'guru@knbi.sch.id', 'role' => 'guru']);
        User::factory()->create(['name' => 'Raka Siswa', 'email' => 'siswa@knbi.sch.id', 'role' => 'siswa']);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_dapat_mengakses_grafik(): void
    {
        $this->seedDemo();

        $admin = User::where('email', 'admin@knbi.sch.id')->firstOrFail();

        // Default (kelas=semua): aggregasi seluruh kelas menjadi 1 set bar ("Semua Kelas")
        $response = $this->actingAs($admin)->getJson('/admin/rekap-grafik');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tanggal',
            'periode',
            'periode_label',
            'rentang_label',
            'hari_sekolah',
            'kelas',
            'kelas_label',
            'labels',
            'hadir',
            'terlambat',
            'izin',
            'sakit',
            'alpa',
        ]);

        $this->assertSame('semua', $response->json('kelas'));
        $this->assertSame(['Semua Kelas'], $response->json('labels'));

        // Data seed demo: 5 Hadir (Raka, Adinda, Budi, Eka, Farhan),
        // 3 Terlambat (Citra, Dimas, Gita), 0 Izin/Sakit/Alpa (hari ini belum lewat)
        $this->assertSame([5], $response->json('hadir'));
        $this->assertSame([3], $response->json('terlambat'));
        $this->assertSame([0], $response->json('izin'));
        $this->assertSame([0], $response->json('sakit'));
        $this->assertSame([0], $response->json('alpa'));
    }

    public function test_grafik_semua_kelas_mengagregasi_total_dan_filter_kelas_tertentu_memisahkan_data(): void
    {
        $this->seedDemo();

        $admin = User::where('email', 'admin@knbi.sch.id')->firstOrFail();
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        // Tambah kelas kedua beserta 1 siswa yang HADIR hari ini
        $kelas2 = SchoolClass::create([
            'name' => 'XI TKJ 1',
            'slug' => 'XI-TKJ-1',
            'grade' => 11,
            'department' => 'Teknik Komputer dan Jaringan',
            'entry_time' => '07:00:00',
            'exit_time' => '15:00:00',
        ]);
        $siswa2 = Student::create([
            'nis' => '11240101',
            'nisn' => '0099887766',
            'name' => 'Siswa Kelas Sebelas',
            'gender' => 'L',
            'school_class_id' => $kelas2->id,
        ]);
        Attendance::create([
            'student_id' => $siswa2->id,
            'school_class_id' => $kelas2->id,
            'date' => $today,
            'check_in_at' => '06:50:00',
            'status' => 'HADIR',
            'kiosk_device' => 'gerbang-kartu',
        ]);

        // 1. kelas=semua -> 1 grup bar aggregasi (5 + 1 = 6 hadir), BUKAN 2 grup bar per kelas
        $resSemua = $this->actingAs($admin)->getJson('/admin/rekap-grafik?kelas=semua');
        $resSemua->assertOk();
        $this->assertSame('semua', $resSemua->json('kelas'));
        $this->assertSame(['Semua Kelas'], $resSemua->json('labels'));
        $this->assertSame([6], $resSemua->json('hadir'));
        $this->assertSame([3], $resSemua->json('terlambat'));

        // 2. kelas=X-TKJ-1 -> 1 grup bar khusus X TKJ 1 (5 hadir, 3 terlambat)
        $resX = $this->actingAs($admin)->getJson('/admin/rekap-grafik?kelas=X-TKJ-1');
        $resX->assertOk();
        $this->assertSame('X-TKJ-1', $resX->json('kelas'));
        $this->assertSame(['X TKJ 1'], $resX->json('labels'));
        $this->assertSame([5], $resX->json('hadir'));
        $this->assertSame([3], $resX->json('terlambat'));

        // 3. kelas=XI-TKJ-1 -> 1 grup bar khusus XI TKJ 1 (1 hadir, 0 terlambat)
        $resXI = $this->actingAs($admin)->getJson('/admin/rekap-grafik?kelas=XI-TKJ-1');
        $resXI->assertOk();
        $this->assertSame('XI-TKJ-1', $resXI->json('kelas'));
        $this->assertSame(['XI TKJ 1'], $resXI->json('labels'));
        $this->assertSame([1], $resXI->json('hadir'));
        $this->assertSame([0], $resXI->json('terlambat'));

        // 4. Halaman /admin/rekapitulasi menampilkan dropdown filter kelas
        $page = $this->actingAs($admin)->get('/admin/rekapitulasi?kelas=XI-TKJ-1');
        $page->assertOk();
        $page->assertSee('Semua Kelas');
        $page->assertSee('X TKJ 1');
        $page->assertSee('XI TKJ 1');
    }

    public function test_guru_dan_siswa_dilarang_akses_grafik(): void
    {
        $this->seedDemo();

        $guru = User::where('email', 'guru@knbi.sch.id')->firstOrFail();
        $siswa = User::where('email', 'siswa@knbi.sch.id')->firstOrFail();

        $this->actingAs($guru)->getJson('/admin/rekap-grafik')->assertStatus(403);
        $this->actingAs($siswa)->getJson('/admin/rekap-grafik')->assertStatus(403);
    }

    public function test_guru_dan_siswa_dilarang_membuka_halaman_rekapitulasi(): void
    {
        $this->seedDemo();

        $guru = User::where('email', 'guru@knbi.sch.id')->firstOrFail();
        $siswa = User::where('email', 'siswa@knbi.sch.id')->firstOrFail();

        $this->actingAs($guru)->get('/admin/rekapitulasi')->assertStatus(403);
        $this->actingAs($siswa)->get('/admin/rekapitulasi')->assertStatus(403);

        $admin = User::where('email', 'admin@knbi.sch.id')->firstOrFail();
        $this->actingAs($admin)->get('/admin/rekapitulasi')->assertStatus(200);
    }
}
