<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Models\Attendance;
use App\Models\FaceEnrollment;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\KioskAttendanceService;
use App\Services\PhotoUploadService;
use App\Support\ExportSanitizer;
use Database\Seeders\DemoAttendanceSeeder;
use Database\Seeders\SchoolClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionHardeningAuditTest extends TestCase
{
    use RefreshDatabase;

    private function validPngBinary(): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data) & 0xFFFFFFFF);
        };

        $ihdr = pack('N2C5', 2, 2, 8, 2, 0, 0, 0);
        $rawScanlines = "\x00\xFF\x00\x00\x00\xFF\x00\x00\xFF\x00\xFF\xFF\x00\x00";
        $idat = (string) gzcompress($rawScanlines);

        return "\x89PNG\r\n\x1A\n"
            .$chunk('IHDR', $ihdr)
            .$chunk('IDAT', $idat)
            .$chunk('IEND', '');
    }

    private function validPngDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode($this->validPngBinary());
    }

    public function test_jurusan_ttt_sudah_diperbaiki_ke_teknik_transmisi_telekomunikasi_dan_relasi_tetap_utuh(): void
    {
        $kelasConfig = collect(config('kelas.tingkat', []))->flatten(1);
        $tttConfigs = $kelasConfig->filter(static fn (array $row): bool => ($row['jurusan'] ?? '') === 'TTT');

        $this->assertCount(2, $tttConfigs);
        foreach ($tttConfigs as $row) {
            $this->assertSame('Teknik Transmisi Telekomunikasi', $row['department']);
        }
        $this->assertTrue(
            $kelasConfig->every(static fn (array $row): bool => ! str_contains(strtolower($row['department']), 'tata udara'))
        );

        // Simulasikan baris lama sebelum migrasi lalu jalankan logika migrasi secara idempoten
        $ttt11 = SchoolClass::create([
            'name' => '11 TTT 1',
            'slug' => '11-TTT-1',
            'department' => 'Teknik Tata Udara & Pendingin',
            'entry_time' => '07:00:00',
            'exit_time' => '15:30:00',
        ]);
        $student = Student::create([
            'name' => 'Siswa TTT Lama',
            'nisn' => '0099990001',
            'gender' => 'L',
            'school_class_id' => $ttt11->id,
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'school_class_id' => $ttt11->id,
            'date' => '2026-11-16',
            'check_in_at' => '06:55:00',
            'status' => 'HADIR',
        ]);

        $migration = require database_path('migrations/2026_11_17_112440_update_ttt_department_to_teknik_transmisi_telekomunikasi.php');
        $migration->up();

        $this->seed(SchoolClassSeeder::class);

        $tttRecords = SchoolClass::whereIn('name', ['11 TTT 1', '12 TTT 1'])->get();
        $this->assertCount(2, $tttRecords);
        foreach ($tttRecords as $record) {
            $this->assertSame('Teknik Transmisi Telekomunikasi', $record->department);
        }
        $this->assertSame(0, SchoolClass::where('department', 'like', '%Tata Udara%')->count());

        $this->assertSame($ttt11->id, $student->fresh()->school_class_id);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'school_class_id' => $ttt11->id,
        ]);
    }

    public function test_dark_mode_terkonfigurasi_tanpa_fouc_pada_halaman_utama_dan_kiosk(): void
    {
        $tailwindConfig = (string) file_get_contents(base_path('tailwind.config.js'));
        $this->assertStringContainsString("darkMode: 'class'", $tailwindConfig);

        $this->seed(DemoAttendanceSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $login = $this->get('/login');
        $login->assertOk();
        $login->assertSee('epresknbi-theme', false);
        $login->assertSee('toggleTheme()', false);
        $login->assertSee('dark:bg-slate-900', false);

        $rekap = $this->actingAs($admin)->get('/admin/rekapitulasi');
        $rekap->assertOk();
        $rekap->assertSee('epresknbi-theme', false);
        $rekap->assertSee('theme:changed', false);
        $rekap->assertSee('dark:bg-slate-800', false);

        $pendaftaran = $this->get('/pendaftaran');
        $pendaftaran->assertOk();
        $pendaftaran->assertSee('epresknbi-theme', false);
        $pendaftaran->assertSee('dark:bg-slate-800', false);

        $kiosk = $this->get('/kiosk');
        $kiosk->assertOk();
        $kiosk->assertSee('epresknbi-theme', false);
        $kiosk->assertSee('toggleTheme()', false);
        $kiosk->assertDontSee('login');
        $kiosk->assertDontSee('dashboard');
    }

    public function test_biometric_endpoints_dan_storage_serve_dikunci_terhadap_guest_dan_non_admin(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('face-photos/1/1.jpg', "\xFF\xD8".str_repeat('a', 150)."\xFF\xD9");

        $this->get('/storage/face-photos/1/1.jpg')->assertNotFound();

        $this->assertFileExists(storage_path('app/private/.htaccess'));
        $this->assertStringContainsString('Require all denied', (string) file_get_contents(storage_path('app/private/.htaccess')));
        $this->assertFileExists(database_path('.htaccess'));
        $this->assertStringContainsString('Require all denied', (string) file_get_contents(database_path('.htaccess')));
        $this->assertStringContainsString('sqlite', (string) file_get_contents(public_path('.htaccess')));

        $this->getJson('/kiosk/face-templates')->assertStatus(401);
        $this->postJson('/kiosk/face-templates', ['student_id' => 1, 'status' => 'empty'])->assertStatus(401);

        foreach (['siswa', 'guru'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/kiosk/face-templates')->assertForbidden();
            $this->actingAs($user)->postJson('/kiosk/face-templates', ['student_id' => 1, 'status' => 'empty'])->assertForbidden();
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/kiosk/face-templates')->assertOk();

        // Guest / tap non-admin tidak boleh menerima 3 foto wajah base64 mentah
        $this->app['auth']->forgetGuards();
        $this->seed(DemoAttendanceSeeder::class);
        $student = Student::where('nisn', '0010240109')->firstOrFail();
        $paths = [
            "face-photos/{$student->id}/1.jpg",
            "face-photos/{$student->id}/2.jpg",
            "face-photos/{$student->id}/3.jpg",
        ];
        foreach ($paths as $path) {
            Storage::disk('local')->put($path, "\xFF\xD8".str_repeat('a', 150)."\xFF\xD9");
        }
        FaceEnrollment::create([
            'student_id' => $student->id,
            'photo_1_path' => $paths[0],
            'photo_2_path' => $paths[1],
            'photo_3_path' => $paths[2],
            'consented_at' => now(),
        ]);

        $guestTap = $this->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-009']);
        $guestTap->assertOk()->assertJson([
            'success' => true,
            'requires_face_verify' => true,
        ]);
        $this->assertSame([], $guestTap->json('face_photos'));

        $adminTap = $this->actingAs($admin)->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-009']);
        $adminTap->assertOk()->assertJson(['requires_face_verify' => true]);
        $this->assertCount(3, $adminTap->json('face_photos'));
    }

    public function test_security_headers_production_debug_guard_idor_dan_anti_replay(): void
    {
        $response = $this->get('/login');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        $this->assertInstanceOf(SecurityHeadersMiddleware::class, app(SecurityHeadersMiddleware::class));

        // Guard debug mode saat APP_ENV=production
        $originalEnv = $this->app['env'];
        $originalDebug = Config::get('app.debug');
        $originalDebugMode = Config::get('app.debug_mode');
        try {
            Config::set('app.debug', true);
            Config::set('app.debug_mode', true);
            $this->app['env'] = 'production';
            $this->assertFalse(isDebugMode());
        } finally {
            $this->app['env'] = $originalEnv;
            Config::set('app.debug', $originalDebug);
            Config::set('app.debug_mode', $originalDebugMode);
        }

        // Horizontal IDOR: akun siswa yang belum tertaut tidak boleh membaca/mengubah siswa lain
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');
        $unlinkedSiswa = User::factory()->create(['role' => 'siswa', 'student_id' => null]);

        $this->actingAs($unlinkedSiswa)->get('/siswa/riwayat-absensi')->assertForbidden();
        $this->actingAs($unlinkedSiswa)->getJson('/siswa/pendaftaran-wajah/status')
            ->assertOk()
            ->assertJson(['terdaftar' => false]);
        $this->actingAs($unlinkedSiswa)->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
            ],
        ])->assertUnprocessable();
        $this->assertSame(0, FaceEnrollment::count());

        // Anti-replay pada alur tap kartu -> verifikasi wajah
        Cache::flush();
        $student = Student::where('nisn', '0010240109')->firstOrFail();
        $paths = [
            "face-photos/{$student->id}/1.jpg",
            "face-photos/{$student->id}/2.jpg",
            "face-photos/{$student->id}/3.jpg",
        ];
        foreach ($paths as $path) {
            Storage::disk('local')->put($path, "\xFF\xD8".str_repeat('a', 150)."\xFF\xD9");
        }
        FaceEnrollment::create([
            'student_id' => $student->id,
            'photo_1_path' => $paths[0],
            'photo_2_path' => $paths[1],
            'photo_3_path' => $paths[2],
            'consented_at' => now(),
        ]);

        $tapRes = $this->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-009'])->assertOk();
        $verifyToken = (string) $tapRes->json('verify_token');
        $this->assertNotSame('', $verifyToken);

        // Permintaan tanpa token wajib ditolak langsung di validasi request
        $this->postJson('/kiosk/face-verify', [
            'student_id' => $student->id,
            'match_score' => 0.92,
        ])->assertStatus(422)->assertJsonValidationErrors(['verify_token']);

        // Permintaan sah pertama berhasil
        $this->postJson('/kiosk/face-verify', [
            'student_id' => $student->id,
            'match_score' => 0.92,
            'verify_token' => $verifyToken,
        ])->assertOk()->assertJson(['success' => true, 'type' => 'check_in']);

        // Replay token yang sama wajib ditolak
        $this->postJson('/kiosk/face-verify', [
            'student_id' => $student->id,
            'match_score' => 0.92,
            'verify_token' => $verifyToken,
        ])->assertStatus(422)->assertJson(['type' => 'replay_detected']);
    }

    public function test_polyglot_upload_dan_formula_csv_header_injection_diblokir(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $polyglotJpeg = "\xFF\xD8\xFF\xE0".str_repeat('A', 60)."<?php system('id'); ?>".str_repeat('B', 60)."\xFF\xD9";
        $polyglotDataUrl = 'data:image/jpeg;base64,'.base64_encode($polyglotJpeg);

        $res = $this->postJson('/pendaftaran', [
            'name' => 'Uji Polyglot',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0099112233',
            'setuju' => true,
            'card_uid' => 'CARD-POLY-001',
            'photos' => [
                $polyglotDataUrl,
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
            ],
        ]);

        $res->assertUnprocessable();
        $this->assertDatabaseMissing('students', ['nisn' => '0099112233']);

        // Tolak file upload ganda / skrip berbahaya di PhotoUploadService
        $photoService = app(PhotoUploadService::class);
        $doubleExt = UploadedFile::fake()->createWithContent('avatar.php.png', $this->validPngBinary());
        $this->assertNull($photoService->decodeAndValidate($doubleExt));

        $polyglotPngFile = UploadedFile::fake()->createWithContent('avatar.png', $this->validPngBinary().'<?php phpinfo(); ?>');
        $this->assertNull($photoService->decodeAndValidate($polyglotPngFile));

        // Sanitasi formula injection Excel & CRLF header injection
        $this->assertSame("'=cmd|'/C calc'!A0", ExportSanitizer::sanitizeCell("=cmd|'/C calc'!A0"));
        $this->assertSame("'+SUM(A1:A5)", ExportSanitizer::sanitizeCell('+SUM(A1:A5)'));
        $this->assertSame("'@cmd", ExportSanitizer::sanitizeCell('@cmd'));
        $this->assertSame('-50%', ExportSanitizer::sanitizeCell('-50%'));
        $this->assertSame('-12', ExportSanitizer::sanitizeCell('-12'));
        $this->assertSame('-', ExportSanitizer::sanitizeCell('-'));

        $safeFilename = ExportSanitizer::sanitizeFilename("rekap\r\nX-Injected: evil.xlsx");
        $this->assertStringNotContainsString("\r", $safeFilename);
        $this->assertStringNotContainsString("\n", $safeFilename);
    }

    public function test_concurrency_kiosk_checkin_hanya_mencatat_satu_kali_dan_daftar_siswa_bebas_n_plus_1(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $student = Student::where('nisn', '0010240109')->firstOrFail();
        $kioskService = app(KioskAttendanceService::class);

        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = $kioskService->recordAttendance($student, null, 'gerbang-kartu', false);
        }

        $this->assertSame(200, $results[0]->getStatusCode());
        for ($i = 1; $i < 20; $i++) {
            $this->assertSame(429, $results[$i]->getStatusCode());
        }

        $todayRecords = Attendance::where('student_id', $student->id)->get();
        $this->assertCount(1, $todayRecords);
        $this->assertNull($todayRecords->first()->check_out_at);

        // Pastikan Student::preloadCardLabels() membuat query daftar siswa konstan O(1) saat jumlah siswa bertambah
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        $measureQueryCount = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get('/admin/data-siswa')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $queriesBefore = $measureQueryCount();

        for ($i = 1; $i <= 15; $i++) {
            $extraStudent = Student::create([
                'name' => "Siswa Bebas NPlus1 {$i}",
                'nisn' => sprintf('0088800%03d', $i),
                'gender' => 'L',
                'school_class_id' => $kelas->id,
            ]);
            RfidCard::create([
                'student_id' => $extraStudent->id,
                'card_uid' => sprintf('CARD-NP1-%03d', $i),
                'is_active' => true,
            ]);
        }

        $queriesAfter = $measureQueryCount();
        $this->assertLessThanOrEqual($queriesBefore + 2, $queriesAfter);
    }
}
