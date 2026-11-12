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
        $admin = User::where('email', 'admin@knbi.sch.id')->firstOrFail();

        $login = $this->get(route('login'))->assertOk();
        $login->assertSee('<title>E-PRESKNBI</title>', false);
        $login->assertDontSee('SIAP');

        $dashboard = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $dashboard->assertSee('<title>E-PRESKNBI</title>', false);
        $dashboard->assertSee('E-PRESKNBI — SMK KARYA NUGRAHA');
        $dashboard->assertDontSee('SIAP');

        $kiosk = $this->get(route('kiosk.gerbang'))->assertOk();
        $kiosk->assertSee('E-PRESKNBI');
        $kiosk->assertDontSee('SIAP');
    }
}
