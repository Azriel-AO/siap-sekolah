<?php

namespace Tests\Feature;

use App\Http\Controllers\KioskController;
use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\RekapRingkasanPeriode;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class BugHunterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('email', 'admin@knbi.sch.id')->first()
            ?? User::factory()->create(['email' => 'admin@knbi.sch.id', 'role' => 'admin']);
    }

    private function fakeJpegDataUrl(string $seed = 'x'): string
    {
        $binary = "\xFF\xD8".str_repeat($seed, 160)."\xFF\xD9";

        return 'data:image/jpeg;base64,'.base64_encode($binary);
    }

    public function test_kiosk_rapid_tap_5x_dalam_1_detik_hanya_mencatat_1_absensi_dan_sisanya_cooldown(): void
    {
        $this->seed(DatabaseSeeder::class);

        $kelas = SchoolClass::firstOrFail();
        $student = Student::create([
            'school_class_id' => $kelas->id,
            'nis' => '10990001',
            'nisn' => '0010990001',
            'name' => 'Siswa Rapid Tap',
            'gender' => 'L',
        ]);
        RfidCard::create([
            'student_id' => $student->id,
            'card_uid' => 'CARD-RAPID-001',
            'is_active' => true,
        ]);

        $statuses = [];
        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson(route('kiosk.tap'), [
                'card_uid' => 'CARD-RAPID-001',
                'direct' => 1,
            ]);
            $statuses[] = $res->status();
        }

        // Tap pertama 200 (check_in), 4 tap berikutnya dalam <1 detik wajib 429 (cooldown)
        $this->assertSame([200, 429, 429, 429, 429], $statuses);
        $this->assertSame(1, Attendance::where('student_id', $student->id)->count());
    }

    public function test_kiosk_absen_saat_kelas_siswa_terhapus_tidak_crash_500(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->admin();

        $kelas = SchoolClass::firstOrFail();
        $student = Student::create([
            'school_class_id' => $kelas->id,
            'nis' => '10990002',
            'nisn' => '0010990002',
            'name' => 'Siswa Tanpa Kelas',
            'gender' => 'L',
        ]);
        RfidCard::create([
            'student_id' => $student->id,
            'card_uid' => 'CARD-ORPHAN-001',
            'is_active' => true,
        ]);

        // 1. Admin mencoba menghapus kelas yang masih punya siswa -> dicegah oleh AdminClassController
        $this->actingAs($admin)
            ->delete(route('admin.kelas.destroy', $kelas))
            ->assertRedirect(route('admin.kelas.index'))
            ->assertSessionHas('error');

        // 2. Bila relasi schoolClass bernilai null (data yatim/inkonsisten), prosesAbsensi() tidak boleh crash 500
        $orphanStudent = clone $student;
        $orphanStudent->setRelation('schoolClass', null);

        $method = new ReflectionMethod(KioskController::class, 'prosesAbsensi');
        $response = $method->invoke(new KioskController, $orphanStudent, null, 'gerbang-kartu');

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame('class_not_found', $data['type']);
    }

    public function test_kiosk_proses_absensi_terisolasi_dari_record_tanggal_masa_depan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $kelas = SchoolClass::firstOrFail();
        $student = Student::create([
            'school_class_id' => $kelas->id,
            'nis' => '10990003',
            'nisn' => '0010990003',
            'name' => 'Siswa Masa Depan',
            'gender' => 'P',
        ]);
        RfidCard::create([
            'student_id' => $student->id,
            'card_uid' => 'CARD-FUTURE-001',
            'is_active' => true,
        ]);

        // Ada record anomali di tanggal masa depan (besok)
        $besok = Carbon::now('Asia/Jakarta')->addDay()->toDateString();
        Attendance::create([
            'student_id' => $student->id,
            'school_class_id' => $kelas->id,
            'date' => $besok,
            'check_in_at' => '07:00:00',
            'status' => 'HADIR',
            'last_tap_at' => Carbon::now('Asia/Jakarta')->subMinutes(10),
            'kiosk_device' => 'gerbang-kartu',
        ]);

        // Tap hari ini tetap wajib membuat check_in untuk tanggal server hari ini
        $res = $this->postJson(route('kiosk.tap'), [
            'card_uid' => 'CARD-FUTURE-001',
            'direct' => 1,
        ]);

        $res->assertOk();
        $res->assertJson([
            'success' => true,
            'type' => 'check_in',
        ]);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'date' => Carbon::now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function test_pendaftaran_menolak_duplikasi_nisn_payload_kosong_dan_script_tag_xss(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $kelas = SchoolClass::firstOrFail();
        Student::create([
            'school_class_id' => $kelas->id,
            'nis' => '0011223344',
            'nisn' => '0011223344',
            'name' => 'Siswa Eksisting',
            'gender' => 'L',
        ]);

        // 1. Submit tanpa foto wajah, tanpa sidik jari, tanpa kartu -> 422 rapi, bukan 500
        $emptyBiometric = $this->postJson(route('pendaftaran.simpan'), [
            'name' => 'Siswa Tanpa Alat',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0099887711',
            'setuju' => true,
        ]);
        $emptyBiometric->assertStatus(422);
        $emptyBiometric->assertJsonValidationErrors(['card_uid', 'photos']);

        // 2. Submit dengan NISN duplikat -> 422
        $dupNisn = $this->postJson(route('pendaftaran.simpan'), [
            'name' => 'Siswa Duplikat',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0011223344',
            'setuju' => true,
            'card_uid' => 'CARD-DUP-001',
            'photos' => [
                $this->fakeJpegDataUrl('1'),
                $this->fakeJpegDataUrl('2'),
                $this->fakeJpegDataUrl('3'),
            ],
        ]);
        $dupNisn->assertStatus(422);
        $dupNisn->assertJsonValidationErrors(['nisn']);

        // 3. Submit nama berisi script tag / HTML berbahaya -> wajib ditolak 422
        $xssAttempt = $this->postJson(route('pendaftaran.simpan'), [
            'name' => '<script>alert("xss")</script>Budi',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0055667788',
            'setuju' => true,
            'card_uid' => 'CARD-XSS-001',
            'photos' => [
                $this->fakeJpegDataUrl('1'),
                $this->fakeJpegDataUrl('2'),
                $this->fakeJpegDataUrl('3'),
            ],
        ]);
        $xssAttempt->assertStatus(422);
        $xssAttempt->assertJsonValidationErrors(['name']);
        $this->assertDatabaseMissing('students', ['nisn' => '0055667788']);
    }

    public function test_export_excel_kelas_tanpa_siswa_dan_rentang_tanggal_terbalik(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->admin();
        $kelasKosong = SchoolClass::firstOrFail();

        // 1. Export Excel rekap & daftar hadir untuk kelas yang tidak punya siswa -> 200 file .xlsx valid
        $rekapExcel = $this->actingAs($admin)->get(route('admin.rekap.excel', [
            'kelas' => $kelasKosong->slug,
            'periode' => 'minggu',
        ]));
        $rekapExcel->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $rekapExcel->headers->get('Content-Type'));

        $daftarHadirExcel = $this->actingAs($admin)->get(route('admin.daftar-hadir.excel', [
            'kelas' => $kelasKosong->slug,
            'bulan' => 11,
            'tahun' => 2026,
        ]));
        $daftarHadirExcel->assertOk();

        // 2. Rentang tanggal terbalik (end < start) pada RekapRingkasanPeriode
        $student = Student::create([
            'school_class_id' => $kelasKosong->id,
            'nis' => '10990009',
            'nisn' => '0010990009',
            'name' => 'Siswa Rentang Terbalik',
            'gender' => 'L',
        ]);
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        Attendance::create([
            'student_id' => $student->id,
            'school_class_id' => $kelasKosong->id,
            'date' => $today->toDateString(),
            'check_in_at' => '06:50:00',
            'status' => 'HADIR',
            'last_tap_at' => $today->copy()->setTime(6, 50),
            'kiosk_device' => 'gerbang-kartu',
        ]);

        // Panggil dengan start > end (terbalik)
        $startTerbalik = $today->copy()->addDays(2);
        $endTerbalik = $today->copy()->subDays(2);
        $ringkasan = RekapRingkasanPeriode::hitungPerSiswa(
            collect([$student]),
            $startTerbalik,
            $endTerbalik,
            5
        );

        $this->assertSame(1, $ringkasan[$student->id]['hadir']);
    }

    public function test_rekap_harian_guru_tidak_crash_500_saat_belum_ada_data_kelas(): void
    {
        $admin = User::factory()->create(['email' => 'admin@knbi.sch.id', 'role' => 'admin']);
        SchoolClass::query()->delete();

        $resHtml = $this->actingAs($admin)->get(route('guru.rekap'));
        $this->assertNotSame(500, $resHtml->status());
        $resHtml->assertStatus(404);

        $resJson = $this->actingAs($admin)->getJson(route('guru.rekap.data'));
        $this->assertNotSame(500, $resJson->status());
        $resJson->assertStatus(404);
    }

    public function test_keamanan_akses_route_admin_dan_proteksi_csrf_kiosk(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siswaUser = User::factory()->create([
            'name' => 'Siswa Biasa',
            'email' => 'siswa.biasa@knbi.sch.id',
            'role' => 'siswa',
        ]);

        $adminRoutes = [
            route('admin.siswa.index'),
            route('admin.kelas.index'),
            route('admin.rekapitulasi'),
            route('admin.kehadiran.manual'),
            route('admin.daftar-hadir'),
            route('admin.status-sistem'),
        ];

        // 1. Tanpa login -> semua route /admin/* redirect ke /login
        foreach ($adminRoutes as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        // 2. Login sebagai siswa biasa -> semua route /admin/* ditolak 403
        foreach ($adminRoutes as $url) {
            $this->actingAs($siswaUser)->get($url)->assertStatus(403);
        }

        // 3. Route POST /kiosk/tap berada di grup middleware 'web' yang memiliki proteksi CSRF
        $kioskTapRoute = Route::getRoutes()->getByName('kiosk.tap');
        $this->assertNotNull($kioskTapRoute);
        $this->assertContains('web', $kioskTapRoute->gatherMiddleware());

        // Verifikasi middleware CSRF menolak POST /kiosk/tap tanpa CSRF token (TokenMismatchException -> HTTP 419)
        $csrfMiddleware = new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        $request = Request::create('/kiosk/tap', 'POST', ['card_uid' => 'CARD-NO-CSRF']);
        $request->setLaravelSession($this->app['session']->driver());

        $this->expectException(TokenMismatchException::class);
        $csrfMiddleware->handle($request, fn () => response('ok'));
    }
}
