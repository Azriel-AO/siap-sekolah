<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavHighlightTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@knbi.sch.id')->firstOrFail();
    }

    private function assertLinkAktif($response, string $label): void
    {
        $html = (string) $response->getContent();
        preg_match('/<a\b[^>]*>\s*' . preg_quote($label, '#') . '\s*<\/a>/s', $html, $m);
        $this->assertNotEmpty($m, "Link '{$label}' tidak ditemukan di HTML.");
        $this->assertStringContainsString(
            'aria-current="page"',
            $m[0],
            "Link '{$label}' seharusnya aktif (aria-current=\"page\")."
        );
    }

    private function assertLinkTidakAktif($response, string $label): void
    {
        $html = (string) $response->getContent();
        preg_match('/<a\b[^>]*>\s*' . preg_quote($label, '#') . '\s*<\/a>/s', $html, $m);
        if ($m) {
            $this->assertStringNotContainsString(
                'aria-current="page"',
                $m[0],
                "Link '{$label}' tidak boleh aktif."
            );
        }
    }

    public function test_beranda_aktif_hanya_di_dashboard(): void
    {
        $this->seedDemo();
        $admin = $this->admin();

        // Di dashboard: Beranda yang aktif
        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertStatus(200);
        $this->assertLinkAktif($response, 'Beranda');
        $this->assertLinkTidakAktif($response, 'Data Siswa');
        $this->assertLinkTidakAktif($response, 'Rekapitulasi');

        // Di Data Siswa: Data Siswa yang aktif, Beranda tidak
        $response = $this->actingAs($admin)->get(route('admin.siswa.index'));
        $response->assertStatus(200);
        $this->assertLinkAktif($response, 'Data Siswa');
        $this->assertLinkTidakAktif($response, 'Beranda');
        $this->assertLinkTidakAktif($response, 'Rekapitulasi');

        // Di Rekapitulasi: Rekapitulasi yang aktif, Beranda tidak
        $response = $this->actingAs($admin)->get(route('admin.rekapitulasi'));
        $response->assertStatus(200);
        $this->assertLinkAktif($response, 'Rekapitulasi');
        $this->assertLinkTidakAktif($response, 'Beranda');
        $this->assertLinkTidakAktif($response, 'Data Siswa');
    }

    public function test_modal_hapus_pakai_x_cloak(): void
    {
        $this->seedDemo();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.siswa.index'));
        $response->assertStatus(200);

        $this->assertStringContainsString('x-show="modalHapus" x-cloak', (string) $response->getContent());
    }
}
