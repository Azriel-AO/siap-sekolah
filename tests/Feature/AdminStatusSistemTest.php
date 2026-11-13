<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminStatusSistemTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@knbi.sch.id')->firstOrFail();
    }

    private function guru(): User
    {
        return User::where('email', 'guru@knbi.sch.id')->firstOrFail();
    }

    private function siswa(): User
    {
        return User::where('email', 'siswa@knbi.sch.id')->firstOrFail();
    }

    public function test_halaman_status_sistem_hanya_bisa_diakses_admin(): void
    {
        $this->seedDemo();

        $this->get('/admin/status-sistem')->assertRedirect('/login');
        $this->actingAs($this->guru())->get('/admin/status-sistem')->assertStatus(403);
        $this->actingAs($this->siswa())->get('/admin/status-sistem')->assertStatus(403);
        $this->actingAs($this->admin())->get('/admin/status-sistem')->assertStatus(200);
    }

    public function test_status_sistem_menampilkan_hijau_saat_bridge_terhubung(): void
    {
        $this->seedDemo();

        config([
            'fingerprint.driver' => 'bridge',
            'fingerprint.base_url' => 'http://127.0.0.1:8787',
        ]);

        Http::fake([
            'http://127.0.0.1:8787/estado' => Http::response([
                'conectado' => true,
                'modelo' => 'ZK9500',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/status-sistem');

        $response->assertOk();
        $response->assertSee('Status Sistem');
        $response->assertSee('Server Web & SQLite Siap');
        $response->assertSee('BRIDGE');
        $response->assertSee('Terhubung');
        $response->assertSee('ZK9500');
        $response->assertSee('Muat ulang');
        $response->assertDontSee('Sensor/bridge belum aktif');
    }

    public function test_status_sistem_menampilkan_merah_saat_bridge_mati(): void
    {
        $this->seedDemo();

        config([
            'fingerprint.driver' => 'bridge',
            'fingerprint.base_url' => 'http://127.0.0.1:8787',
        ]);

        Http::fake([
            'http://127.0.0.1:8787/estado' => Http::response([
                'conectado' => false,
                'modelo' => null,
            ], 503),
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/status-sistem');

        $response->assertOk();
        $response->assertSee('BRIDGE');
        $response->assertSee('Terputus');
        $response->assertSee('Sensor/bridge belum aktif');
        $response->assertSee('Muat ulang');
    }

    public function test_script_box_autostart_tersedia(): void
    {
        $this->assertFileExists(base_path('box/mulai-sistem.bat'));
        $this->assertFileExists(base_path('box/watchdog.bat'));
        $this->assertFileExists(base_path('box/pasang-autostart.bat'));
    }
}
