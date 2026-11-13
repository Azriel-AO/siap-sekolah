<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Http;

/**
 * Abstraksi sensor sidik jari ZK9500 (via bridge HTTP di 127.0.0.1:8787)
 * dengan driver mock agar seluruh alur bisa diuji TANPA alat fisik.
 *
 * Konfigurasi (config/fingerprint.php / .env):
 *   FINGERPRINT_DRIVER=mock|bridge
 *   FINGERPRINT_BASE_URL=http://127.0.0.1:8787
 *   FINGERPRINT_TIMEOUT=25
 *
 * Semua pemanggilan dilakukan SERVER-SIDE lewat Http facade —
 * browser TIDAK PERNAH memanggil bridge langsung (hindari CORS).
 */
class FingerprintService
{
    public function __construct(
        private readonly string $driver = 'mock',
        private readonly string $baseUrl = 'http://127.0.0.1:8787',
        private readonly int $timeout = 25,
    ) {}

    public static function dariConfig(): self
    {
        return new self(
            (string) config('fingerprint.driver', 'mock'),
            (string) config('fingerprint.base_url', 'http://127.0.0.1:8787'),
            (int) config('fingerprint.timeout', 25),
        );
    }

    /**
     * Status sensor untuk ditampilkan di UI (tidak melempar exception).
     *
     * @return array{conectado: bool, modelo: ?string, driver: string}
     */
    public function status(): array
    {
        if ($this->driver !== 'bridge') {
            return ['conectado' => true, 'modelo' => 'Mock (sensor virtual)', 'driver' => 'mock'];
        }

        try {
            $res = Http::timeout(5)->get($this->baseUrl.'/estado');
            if ($res->successful() && ($res->json('conectado') ?? false) === true) {
                return ['conectado' => true, 'modelo' => $res->json('modelo'), 'driver' => 'bridge'];
            }
        } catch (\Throwable) {
            // Bridge tidak jalan / timeout / sensor error → anggap tidak terhubung
        }

        return ['conectado' => false, 'modelo' => null, 'driver' => 'bridge'];
    }

    /**
     * Identifikasi sidik jari yang sedang diletakkan pada sensor.
     *
     * @param  bool  $auto  true bila dipanggil loop otomatis kiosk.
     *                      Di driver mock + auto=1, identify SELALU null (anti spam absen).
     *                      Di driver bridge, auto=1 diperlakukan identik dengan pemanggilan biasa.
     * @return string|null huellaId siswa, null bila tidak dikenali / sensor error / timeout
     */
    public function identify(bool $auto = false): ?string
    {
        return $this->identifyDetailed($auto)['huellaId'];
    }

    /**
     * Identifikasi sidik jari dengan rincian status kegagalan agar kiosk dapat
     * membedakan bridge mati vs timeout vs sensor idle (tanpa jari) vs sidik jari tidak dikenali.
     *
     * Kontrak reason dari bridge /identificar saat ok:false:
     * - reason="not_matched" -> 'fingerprint_unknown' (jari ditempel tapi tidak cocok)
     * - reason="no_finger" atau reason tidak ada (bridge lama) -> 'sensor_idle'
     *
     * @return array{status: 'ok'|'bridge_mati'|'sensor_timeout'|'sensor_idle'|'fingerprint_unknown', huellaId: ?string}
     */
    public function identifyDetailed(bool $auto = false): array
    {
        if ($this->driver !== 'bridge') {
            if ($auto) {
                return ['status' => 'sensor_idle', 'huellaId' => null];
            }

            $huellaId = $this->mockIdentify();

            return is_string($huellaId) && $huellaId !== ''
                ? ['status' => 'ok', 'huellaId' => $huellaId]
                : ['status' => 'fingerprint_unknown', 'huellaId' => null];
        }

        try {
            $res = Http::timeout($this->timeout)->get($this->baseUrl.'/identificar');
            if ($res->status() === 408 || $res->status() === 504) {
                return ['status' => 'sensor_timeout', 'huellaId' => null];
            }
            if (! $res->successful()) {
                return ['status' => 'bridge_mati', 'huellaId' => null];
            }

            $huellaId = $res->json('huellaId');
            if (is_string($huellaId) && $huellaId !== '') {
                return ['status' => 'ok', 'huellaId' => $huellaId];
            }

            if ($res->json('timeout') === true) {
                return ['status' => 'sensor_timeout', 'huellaId' => null];
            }

            $error = $res->json('error');
            if (is_string($error) && $error !== '') {
                $errorLower = strtolower($error);
                if (str_contains($errorLower, 'timeout') || str_contains($errorLower, 'timed out')) {
                    return ['status' => 'sensor_timeout', 'huellaId' => null];
                }

                return ['status' => 'bridge_mati', 'huellaId' => null];
            }

            $reason = $res->json('reason');
            if ($reason === 'not_matched') {
                return ['status' => 'fingerprint_unknown', 'huellaId' => null];
            }

            // reason === 'no_finger' ATAU tidak ada field reason (kompatibel bridge lama)
            return ['status' => 'sensor_idle', 'huellaId' => null];
        } catch (\Throwable $e) {
            if ($this->isTimeoutException($e)) {
                return ['status' => 'sensor_timeout', 'huellaId' => null];
            }

            return ['status' => 'bridge_mati', 'huellaId' => null];
        }
    }

    /**
     * Deteksi apakah exception HTTP berasal dari batas waktu tunggu (timeout).
     */
    private function isTimeoutException(\Throwable $e): bool
    {
        $pesan = strtolower($e->getMessage());

        return str_contains($pesan, 'timed out')
            || str_contains($pesan, 'timeout')
            || str_contains($pesan, 'curl error 28');
    }

    /**
     * Daftarkan sidik jari baru ke sensor.
     *
     * @return array{huellaId: string, template: string|null}|null null bila sensor error
     */
    public function enroll(): ?array
    {
        if ($this->driver !== 'bridge') {
            return [
                'huellaId' => 'MOCK-'.strtoupper(uniqid()),
                'template' => base64_encode(random_bytes(32)),
            ];
        }

        try {
            $res = Http::timeout($this->timeout)->post($this->baseUrl.'/enrolar');
            if (! $res->successful()) {
                return null;
            }
            $huellaId = $res->json('huellaId');
            if (! is_string($huellaId) || $huellaId === '') {
                return null;
            }

            return [
                'huellaId' => $huellaId,
                'template' => $res->json('template'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mock: kembalikan fingerprint_id siswa demo yang sudah terdaftar,
     * paling awal didaftarkan (deterministik untuk testing).
     */
    private function mockIdentify(): ?string
    {
        return Student::whereNotNull('fingerprint_id')->orderBy('id')->value('fingerprint_id');
    }
}
