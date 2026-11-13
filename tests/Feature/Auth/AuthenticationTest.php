<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('E-PRESKNBI — SMK KARYA NUGRAHA');
        $response->assertSee('Masuk');
    }

    public function test_login_screen_memakai_brand_baru_tanpa_siap(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('E-PRESKNBI');
        $response->assertDontSee('SIAP');
    }

    public function test_root_url_redirects_to_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_unauthenticated_user_is_redirected_from_dashboard_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_login_and_see_admin_dashboard(): void
    {
        $this->seed(UserSeeder::class);

        $response = $this->post('/login', [
            'email' => 'admin@knbi.sch.id',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Admin KNBI');
        $dashboardResponse->assertSee('Administrator');
        $dashboardResponse->assertSee('E-PRESKNBI — SMK KARYA NUGRAHA');
        $dashboardResponse->assertSee('Data Siswa');
        $dashboardResponse->assertSee('Data Kelas');
        $dashboardResponse->assertSee('Kiosk Absen');
        $dashboardResponse->assertSee('Daftar Hadir');
        $dashboardResponse->assertDontSee('Laporan');
        $dashboardResponse->assertDontSee('Jadwal Kelas');
        $dashboardResponse->assertDontSee('Milestone 0');
    }

    public function test_halaman_login_tidak_memuat_email_dan_banner_akun_demo(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('Akun Contoh Pengujian');
        $response->assertDontSee('admin@knbi.sch.id');
        $response->assertDontSee('guru@knbi.sch.id');
        $response->assertDontSee('siswa@knbi.sch.id');
    }

    public function test_user_seeder_hanya_membuat_admin_tanpa_user_demo(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame(1, User::where('email', 'admin@knbi.sch.id')->where('role', 'admin')->count());
        $this->assertSame(0, User::whereIn('role', ['guru', 'siswa'])->count());
    }

    public function test_guru_login_ditolak_dan_tidak_ada_session(): void
    {
        User::factory()->create([
            'email' => 'guru@knbi.sch.id',
            'role' => 'guru',
        ]);

        $response = $this->post('/login', [
            'email' => 'guru@knbi.sch.id',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Login hanya untuk admin. Siswa tidak memerlukan akun — silakan absen langsung di gerbang.',
        ]);
    }

    public function test_siswa_login_ditolak_dan_tidak_ada_session(): void
    {
        User::factory()->create([
            'email' => 'siswa@knbi.sch.id',
            'role' => 'siswa',
        ]);

        $response = $this->post('/login', [
            'email' => 'siswa@knbi.sch.id',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Login hanya untuk admin. Siswa tidak memerlukan akun — silakan absen langsung di gerbang.',
        ]);
    }

    public function test_guru_dan_siswa_dilarang_mengakses_dashboard(): void
    {
        $guru = User::factory()->create(['role' => 'guru']);
        $siswa = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($guru)->get('/dashboard')->assertStatus(403);
        $this->actingAs($siswa)->get('/dashboard')->assertStatus(403);
    }

    public function test_invalid_password_returns_indonesian_error_message(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'salah123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Email atau kata sandi salah. Silakan coba lagi.',
        ]);
    }

    public function test_empty_fields_returns_indonesian_validation_message(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Kolom ini wajib diisi.',
            'password' => 'Kolom ini wajib diisi.',
        ]);
    }

    public function test_invalid_email_format_returns_indonesian_validation_message(): void
    {
        $response = $this->post('/login', [
            'email' => 'bukan-email',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Format email tidak valid.',
        ]);
    }

    public function test_users_can_logout_and_redirect_to_login(): void
    {
        $user = User::factory()->create(['role' => 'siswa']);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_has_cache_prevention_headers(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
