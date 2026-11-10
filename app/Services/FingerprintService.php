<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Abstraksi sensor sidik jari ZK9500 (via bridge HTTP di 127.0.0.1:8787)
 * dengan driver mock agar seluruh alur bisa diuji TANPA alat fisik.
 *
 * Konfigurasi (.env):
 *   FINGERPRINT_DRIVER=mock|bridge
 *   FINGERPRINT_BASE_URL=http://127.0.0.1:8787
 *
 * Semua pemanggilan dilakukan SERVER-SIDE lewat Http facade —
 * browser TIDAK PERNAH memanggil bridge langsung (hindari CORS).
 */
class FingerprintService
{
    public function __construct(
        private readonly string $driver = 'mock',
        private readonly string $baseUrl = 'http://127.0.0.1:8787',
        private readonly int $timeout = 10,
    ) {
    }

    public static function dariConfig(): self
    {
        return new self(
            (string) env('FINGERPRINT_DRIVER', 'mock'),
            (string) env('FINGERPRINT_BASE_URL', 'http://127.0.0.1:8787'),
            (int) env('FINGERPRINT_TIMEOUT', 10),
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
            $res = Http::timeout(5)->get($this->baseUrl . '/estado');
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
     * @return string|null huellaId siswa, null bila tidak dikenali / sensor error
     */
    public function identify(): ?string
    {
        if ($this->driver !== 'bridge') {
            return $this->mockIdentify();
        }

        try {
            $res = Http::timeout($this->timeout)->get($this->baseUrl . '/identificar');
            if (! $res->successful()) {
                return null;
            }
            $huellaId = $res->json('huellaId');

            return is_string($huellaId) && $huellaId !== '' ? $huellaId : null;
        } catch (\Throwable) {
            return null;
        }
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
                'huellaId' => 'MOCK-' . strtoupper(uniqid()),
                'template' => base64_encode(random_bytes(32)),
            ];
        }

        try {
            $res = Http::timeout($this->timeout)->post($this->baseUrl . '/enrolar');
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
        return \App\Models\Student::whereNotNull('fingerprint_id')->orderBy('id')->value('fingerprint_id');
    }
}
