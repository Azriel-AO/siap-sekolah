<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPendaftaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_admin_menampilkan_tombol_terminal_pendaftaran_di_tab_baru(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'admin@knbi.sch.id')->firstOrFail();

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
}
