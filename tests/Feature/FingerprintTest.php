<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FingerprintService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FingerprintTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    private function fakeJpegDataUrl(string $seed = 'a'): string
    {
        return 'data:image/jpeg;base64,'.base64_encode("\xFF\xD8".str_repeat($seed, 150)."\xFF\xD9");
    }

    private function withDriver(string $driver, callable $callback): mixed
    {
        $lama = config('fingerprint.driver');
        config(['fingerprint.driver' => $driver]);

        try {
            return $callback();
        } finally {
            config(['fingerprint.driver' => $lama]);
        }
    }

    public function test_mock_enrolar_mengembalikan_huella_id_dan_siswa_mendapatkannya(): void
    {
        $this->seedDemo();

        // Enroll via endpoint mock
        $response = $this->postJson('/pendaftaran/fingerprint/enrolar');

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
        $huellaId = $response->json('huellaId');
        $this->assertNotEmpty($huellaId);
        $this->assertStringStartsWith('MOCK-', $huellaId);

        // Simpan pendaftaran memakai huellaId tersebut → siswa menyimpan fingerprint_id
        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $response = $this->postJson('/pendaftaran', [
            'name' => 'Siswa Uji Sidik Jari',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0088776655',
            'setuju' => true,
            'card_uid' => 'CARD-FP-001',
            'fingerprint_id' => $huellaId,
            'photos' => [
                $this->fakeJpegDataUrl('a'),
                $this->fakeJpegDataUrl('b'),
                $this->fakeJpegDataUrl('c'),
            ],
        ]);

        $response->assertOk();
        $this->assertSame($huellaId, Student::where('nisn', '0088776655')->value('fingerprint_id'));
    }

    public function test_mock_identificar_menemukan_siswa(): void
    {
        $this->seedDemo();

        Student::where('nis', '10240109')->update(['fingerprint_id' => 'MOCK-FINGER-999']);

        $response = $this->postJson('/kiosk/fingerprint/identificar', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'type' => 'check_in',
            'student_name' => 'Hendra Wijaya',
        ]);
    }

    public function test_bridge_identificar_sukses_ketemu_siswa(): void
    {
        $this->seedDemo();

        Http::fake(fn ($request) => match (true) {
            str_contains($request->url(), '/identificar') => Http::response(['ok' => true, 'huellaId' => 'BRIDGE-HUELLA-1'], 200),
            default => Http::response(['message' => 'not found'], 404),
        });

        Student::where('nis', '10240109')->update(['fingerprint_id' => 'BRIDGE-HUELLA-1']);

        $response = $this->withDriver('bridge', fn () => $this->postJson('/kiosk/fingerprint/identificar', []));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'type' => 'check_in',
            'student_name' => 'Hendra Wijaya',
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => Student::where('nis', '10240109')->value('id'),
            'kiosk_device' => 'gerbang-fingerprint',
        ]);
    }

    public function test_bridge_sensor_mati_pesan_ramah_tanpa_crash_500(): void
    {
        $this->seedDemo();

        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->withDriver('bridge', fn () => $this->postJson('/kiosk/fingerprint/identificar', []));

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
            'type' => 'bridge_mati',
            'message' => 'Sensor belum aktif — hadir dengan tap kartu',
        ]);
    }

    public function test_bridge_timeout_mengembalikan_null_dan_pesan_ramah_tanpa_500(): void
    {
        $this->seedDemo();

        $this->assertSame(25, config('fingerprint.timeout'));

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 25000 milliseconds'));

        $this->withDriver('bridge', function () {
            $service = FingerprintService::dariConfig();
            $this->assertNull($service->identify());
            $this->assertSame('sensor_timeout', $service->identifyDetailed()['status']);

            $response = $this->postJson('/kiosk/fingerprint/identificar', []);

            $response->assertStatus(200);
            $response->assertJson([
                'success' => false,
                'type' => 'sensor_timeout',
                'message' => 'Sensor sedang menunggu jari — coba tempelkan jari lebih lama',
            ]);
        });
    }

    public function test_bridge_huella_tidak_terdaftar_dijawab_tidak_dikenali(): void
    {
        $this->seedDemo();

        // Sensor merespons dengan huellaId yang tidak dimiliki siswa manapun
        Http::fake(fn ($request) => match (true) {
            str_contains($request->url(), '/identificar') => Http::response(['ok' => true, 'huellaId' => 'BRIDGE-ORANG-LAIN'], 200),
            default => Http::response(['message' => 'not found'], 404),
        });

        $response = $this->withDriver('bridge', fn () => $this->postJson('/kiosk/fingerprint/identificar', []));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'type' => 'fingerprint_unknown',
            'message' => 'Sidik jari tidak dikenali. Daftar dulu bila belum terdaftar.',
        ]);
        $response->assertSee('/pendaftaran');
    }

    public function test_bridge_identify_mapping_reason_no_finger_not_matched_dan_tanpa_reason(): void
    {
        $this->seedDemo();

        $payload = ['ok' => false, 'reason' => 'no_finger'];
        Http::fake(function () use (&$payload) {
            return Http::response($payload, 200);
        });

        $this->withDriver('bridge', function () use (&$payload) {
            $service = FingerprintService::dariConfig();

            // 1. reason = no_finger -> sensor_idle
            $this->assertSame('sensor_idle', $service->identifyDetailed()['status']);
            $this->assertNull($service->identify());

            // 2. reason = not_matched -> fingerprint_unknown
            $payload = ['ok' => false, 'reason' => 'not_matched'];
            $this->assertSame('fingerprint_unknown', $service->identifyDetailed()['status']);
            $this->assertNull($service->identify());

            // 3. Tanpa field reason (bridge lama / backward compatible) -> sensor_idle
            $payload = ['ok' => false];
            $this->assertSame('sensor_idle', $service->identifyDetailed()['status']);
            $this->assertNull($service->identify());
        });
    }

    public function test_bridge_enrolar_gagal_pesan_ramah_tanpa_crash_500(): void
    {
        $this->seedDemo();

        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->withDriver('bridge', fn () => $this->postJson('/pendaftaran/fingerprint/enrolar'));

        $response->assertStatus(503);
        $this->assertStringContainsString('Sensor sidik jari tidak tersedia', $response->json('message'));
    }

    public function test_bridge_enrolar_tanpa_jari_ditolak_dan_tidak_membuat_huella_id(): void
    {
        $this->seedDemo();

        // Bridge produksi menjawab 200 + ok:false (tanpa huellaId) bila tidak ada jari / template kosong.
        Http::fake(fn () => Http::response(['ok' => false, 'error' => 'Tidak ada jari terdeteksi. Letakkan jari pada sensor.'], 200));

        $response = $this->withDriver('bridge', fn () => $this->postJson('/pendaftaran/fingerprint/enrolar'));

        $response->assertStatus(503);
        $response->assertJson(['ok' => false]);
        $this->assertNull($response->json('huellaId'));
    }

    public function test_bridge_enrolar_sukses_hanya_bila_bridge_memberi_huella_id(): void
    {
        $this->seedDemo();

        Http::fake(fn () => Http::response(['ok' => true, 'huellaId' => 'BRIDGE-GUID-ASLI', 'template' => 'AAAA'], 200));

        $response = $this->withDriver('bridge', fn () => $this->postJson('/pendaftaran/fingerprint/enrolar'));

        $response->assertOk();
        $response->assertJson(['ok' => true, 'huellaId' => 'BRIDGE-GUID-ASLI']);
    }

    public function test_kiosk_driver_bridge_tanpa_tombol_demo_dan_loop_aktif(): void
    {
        $bridge = $this->withDriver('bridge', fn () => $this->get(route('kiosk.gerbang')));

        $bridge->assertOk();
        $bridge->assertDontSee('DEMO — Simulasi jari');
        $bridge->assertSee('isMockFingerprint: false', false);
    }

    public function test_kiosk_driver_mock_menampilkan_tombol_demo_untuk_presentasi(): void
    {
        $mock = $this->withDriver('mock', fn () => $this->get(route('kiosk.gerbang')));

        $mock->assertOk();
        $mock->assertSee('DEMO — Simulasi jari');
        $mock->assertSee('isMockFingerprint: true', false);
    }
}
