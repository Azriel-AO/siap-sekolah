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
        $response->assertSee('SIAP — SMK Karya Nugraha');
        $response->assertSee('Masuk');
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
        $dashboardResponse->assertSee('SIAP — SMK Karya Nugraha');
        $dashboardResponse->assertSee('Data Siswa');
        $dashboardResponse->assertSee('Jadwal Kelas');
        $dashboardResponse->assertSee('Kiosk Absen');
        $dashboardResponse->assertSee('Laporan');
    }

    public function test_guru_can_login_and_see_guru_dashboard(): void
    {
        $this->seed(UserSeeder::class);

        $response = $this->post('/login', [
            'email' => 'guru@knbi.sch.id',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Bu Sari');
        $dashboardResponse->assertSee('Guru / Wali Kelas');
    }

    public function test_siswa_can_login_and_see_siswa_dashboard(): void
    {
        $this->seed(UserSeeder::class);

        $response = $this->post('/login', [
            'email' => 'siswa@knbi.sch.id',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Raka Siswa');
        $dashboardResponse->assertSee('Siswa');
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
