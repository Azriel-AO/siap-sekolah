<?php

namespace Tests\Feature;

use App\Models\User;
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

        $response = $this->actingAs($admin)->getJson('/admin/rekap-grafik');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tanggal',
            'periode',
            'periode_label',
            'rentang_label',
            'hari_sekolah',
            'labels',
            'hadir',
            'terlambat',
            'izin',
            'sakit',
            'alpa',
        ]);

        // Kelas demo satu-satunya: X TKJ 1
        $this->assertSame(['X TKJ 1'], $response->json('labels'));

        // Data seed demo: 5 Hadir (Raka, Adinda, Budi, Eka, Farhan),
        // 3 Terlambat (Citra, Dimas, Gita), 0 Izin/Sakit/Alpa (hari ini belum lewat)
        $this->assertSame([5], $response->json('hadir'));
        $this->assertSame([3], $response->json('terlambat'));
        $this->assertSame([0], $response->json('izin'));
        $this->assertSame([0], $response->json('sakit'));
        $this->assertSame([0], $response->json('alpa'));
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
