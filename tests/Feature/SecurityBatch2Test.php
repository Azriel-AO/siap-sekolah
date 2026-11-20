<?php

namespace Tests\Feature;

use App\Models\Student;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityBatch2Test extends TestCase
{
    use RefreshDatabase;

    private function validPngDataUrl(): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data) & 0xFFFFFFFF);
        };

        $ihdr = pack('N2C5', 2, 2, 8, 2, 0, 0, 0);
        $idat = (string) gzcompress("\x00\xFF\x00\x00\x00\xFF\x00\x00\xFF\x00\xFF\xFF\x00\x00");

        return 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1A\n".$chunk('IHDR', $ihdr).$chunk('IDAT', $idat).$chunk('IEND', ''));
    }

    public function test_is_debug_mode_mewajibkan_env_aman_serta_app_debug_dan_debug_mode_true(): void
    {
        $originalEnv = $this->app['env'];
        $originalDebug = Config::get('app.debug');
        $originalDebugMode = Config::get('app.debug_mode');

        try {
            Config::set('app.debug', true);
            Config::set('app.debug_mode', true);

            foreach (['local', 'staging', 'testing'] as $allowedEnv) {
                $this->app['env'] = $allowedEnv;
                $this->assertTrue(isDebugMode(), "Environment {$allowedEnv} seharusnya mendukung debug mode.");
            }

            $this->app['env'] = 'production';
            $this->assertFalse(isDebugMode(), 'Production harus selalu mematikan debug mode.');

            $this->app['env'] = 'local';
            Config::set('app.debug', false);
            $this->assertFalse(isDebugMode(), 'isDebugMode wajib false saat app.debug false.');

            Config::set('app.debug', true);
            Config::set('app.debug_mode', false);
            $this->assertFalse(isDebugMode(), 'isDebugMode wajib false saat app.debug_mode false.');
        } finally {
            $this->app['env'] = $originalEnv;
            Config::set('app.debug', $originalDebug);
            Config::set('app.debug_mode', $originalDebugMode);
        }
    }

    public function test_route_debug_simulate_dibatasi_throttle_10_per_menit(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        config(['app.debug' => true, 'app.debug_mode' => true]);
        $student = Student::firstOrFail();

        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson(route('kiosk.debug.simulate'), [
                'student_id' => $student->id,
                'channel' => 'debug',
                'bypass_cooldown' => true,
            ]);
            $response->assertOk();
        }

        $blocked = $this->postJson(route('kiosk.debug.simulate'), [
            'student_id' => $student->id,
            'channel' => 'debug',
        ]);
        $blocked->assertTooManyRequests();
    }

    public function test_route_fingerprint_enrolar_dibatasi_throttle_10_per_menit(): void
    {
        config(['fingerprint.driver' => 'mock']);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('pendaftaran.fingerprint.enrolar'))->assertOk();
        }

        $this->postJson(route('pendaftaran.fingerprint.enrolar'))->assertTooManyRequests();
    }

    public function test_validasi_kartu_duplikat_menghilangkan_pii_nama_siswa_dan_uid(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $existingOwner = Student::where('nisn', '0010240101')->with('schoolClass')->firstOrFail();

        $res = $this->postJson(route('pendaftaran.simpan'), [
            'name' => 'Calon Siswa Uji',
            'gender' => 'L',
            'school_class_id' => $existingOwner->school_class_id,
            'nisn' => '0099776655',
            'setuju' => true,
            'card_uid' => 'CARD-TKJ-001',
            'photos' => [
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
            ],
        ]);

        $res->assertUnprocessable()->assertJsonValidationErrors(['card_uid']);
        $errorMessage = (string) $res->json('errors.card_uid.0');

        $this->assertSame('Kartu dengan UID tersebut sudah terdaftar. Gunakan kartu lain atau hubungi admin.', $errorMessage);
        $this->assertStringNotContainsString($existingOwner->name, $errorMessage);
        $this->assertStringNotContainsString('CARD-TKJ-001', $errorMessage);
    }
}
