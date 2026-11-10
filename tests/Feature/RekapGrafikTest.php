<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapGrafikTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_admin_dapat_mengakses_grafik(): void
    {
        $this->seedDemo();

        $admin = User::where('email', 'admin@knbi.sch.id')->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/admin/rekap-grafik');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tanggal',
            'labels',
            'hadir',
            'terlambat',
            'tidak_hadir',
        ]);

        // Kelas demo satu-satunya: X TKJ 1
        $this->assertSame(['X TKJ 1'], $response->json('labels'));

        // Data seed demo: 5 Hadir (Raka, Adinda, Budi, Eka, Farhan),
        // 3 Terlambat (Citra, Dimas, Gita), 2 Belum Absen (Hendra, Intan)
        $this->assertSame([5], $response->json('hadir'));
        $this->assertSame([3], $response->json('terlambat'));
        $this->assertSame([2], $response->json('tidak_hadir'));
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
