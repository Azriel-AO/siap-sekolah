<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_nama_aplikasi_dari_config_adalah_e_presknbi(): void
    {
        $this->assertSame('E-PRESKNBI', config('app.name'));
    }

    public function test_login_dashboard_dan_kiosk_memakai_brand_e_presknbi_tanpa_siap(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['email' => 'admin@knbi.sch.id', 'role' => 'admin']);

        $login = $this->get(route('login'))->assertOk();
        $login->assertSee('<title>E-PRESKNBI</title>', false);
        $login->assertSee('images/logo-sekolah.png', false);
        $login->assertSee('alt="Logo SMK Karya Nugraha"', false);
        $login->assertSee('<link rel="icon" type="image/png"', false);
        $login->assertSee('<link rel="apple-touch-icon"', false);
        $login->assertDontSee('SIAP');

        $dashboard = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $dashboard->assertSee('<title>E-PRESKNBI</title>', false);
        $dashboard->assertSee('E-PRESKNBI — SMK KARYA NUGRAHA');
        $dashboard->assertSee('images/logo-sekolah.png', false);
        $dashboard->assertSee('alt="Logo SMK Karya Nugraha"', false);
        $dashboard->assertSee('<link rel="icon" type="image/png"', false);
        $dashboard->assertSee('<link rel="apple-touch-icon"', false);
        $dashboard->assertDontSee('SIAP');

        $kiosk = $this->get(route('kiosk.gerbang'))->assertOk();
        $kiosk->assertSee('E-PRESKNBI');
        $kiosk->assertSee('images/logo-sekolah.png', false);
        $kiosk->assertSee('alt="Logo SMK Karya Nugraha"', false);
        $kiosk->assertSee('<link rel="icon" type="image/png"', false);
        $kiosk->assertSee('<link rel="apple-touch-icon"', false);
        $kiosk->assertDontSee('SIAP');

        $pendaftaran = $this->get(route('pendaftaran.index'))->assertOk();
        $pendaftaran->assertSee('E-PRESKNBI');
        $pendaftaran->assertSee('images/logo-sekolah.png', false);
        $pendaftaran->assertSee('alt="Logo SMK Karya Nugraha"', false);
        $pendaftaran->assertSee('<link rel="icon" type="image/png"', false);
        $pendaftaran->assertSee('<link rel="apple-touch-icon"', false);
    }
}
