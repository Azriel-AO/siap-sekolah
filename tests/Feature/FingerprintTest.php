<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
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
        $this->aktifkanPendaftaran();

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
            'type' => 'fingerprint_unavailable',
        ]);
        $this->assertStringContainsString('Sensor sidik jari tidak tersedia', $response->json('message'));
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

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'type' => 'fingerprint_not_found',
        ]);
    }

    public function test_bridge_enrolar_gagal_pesan_ramah_tanpa_crash_500(): void
    {
        $this->seedDemo();
        $this->aktifkanPendaftaran();

        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->withDriver('bridge', fn () => $this->postJson('/pendaftaran/fingerprint/enrolar'));

        $response->assertStatus(503);
        $this->assertStringContainsString('Sensor sidik jari tidak tersedia', $response->json('message'));
    }

    private function aktifkanPendaftaran(): void
    {
        Setting::set('mode_pendaftaran_gerbang', '1');
    }
}
