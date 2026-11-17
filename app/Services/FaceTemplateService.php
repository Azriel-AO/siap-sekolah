<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Layanan sanitasi, penyimpanan, dan pembacaan vektor template wajah 1434 float (v2 Procrustes IOD).
 */
class FaceTemplateService
{
    public const TEMPLATE_VERSION = 'v2-procrustes-iod';

    public const CACHE_KEY = 'kiosk_face_templates';

    public const VECTOR_LENGTH = 1434;

    /**
     * Validasi dan sanitasi array 1434 float mentah menjadi vektor bersih berhingga non-nol.
     *
     * @return list<float>|null
     */
    public function sanitizeVector(mixed $raw): ?array
    {
        if (! is_array($raw) || count($raw) !== self::VECTOR_LENGTH) {
            return null;
        }

        $normalized = [];
        $sumSq = 0.0;

        foreach ($raw as $val) {
            if (! is_numeric($val)) {
                return null;
            }
            $floatVal = (float) $val;
            if (! is_finite($floatVal)) {
                return null;
            }
            $rounded = round($floatVal, 6);
            $normalized[] = $rounded;
            $sumSq += $rounded * $rounded;
        }

        if ($sumSq <= 1e-8) {
            return null;
        }

        return $normalized;
    }

    /**
     * Simpan `template.json` dan `template_meta.json` untuk siswa tertentu pada disk privat 'local'.
     *
     * @param  list<float>  $sanitizedVector
     * @return list<string> Path file yang ditulis (berguna bila transaksi perlu rollback).
     */
    public function storeForStudent(int $studentId, array $sanitizedVector, string $source = 'enrollment'): array
    {
        $disk = Storage::disk('local');
        $templatePath = $this->templatePath($studentId);
        $metaPath = $this->metaPath($studentId);

        $disk->put($templatePath, json_encode($sanitizedVector));
        $disk->put($metaPath, json_encode([
            'version' => self::TEMPLATE_VERSION,
            'status' => 'ok',
            'source' => $source,
            'updated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
        ]));

        return [$templatePath, $metaPath];
    }

    /**
     * Tandai foto pendaftaran siswa tidak memiliki landmark wajah yang dapat diekstrak
     * (misalnya foto dummy debug) agar kiosk tidak terus-menerus memproses ulang.
     */
    public function markUnextractable(int $studentId, string $source = 'kiosk_hydration'): void
    {
        $disk = Storage::disk('local');
        $disk->put($this->metaPath($studentId), json_encode([
            'version' => self::TEMPLATE_VERSION,
            'status' => 'unextractable',
            'source' => $source,
            'updated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
        ]));
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Hapus cache file `template.json` dan `template_meta.json` milik siswa (saat daftar ulang).
     */
    public function clearForStudent(int $studentId): void
    {
        $disk = Storage::disk('local');
        $disk->delete($this->templatePath($studentId));
        $disk->delete($this->metaPath($studentId));
    }

    /**
     * Baca metadata template versi terkini milik siswa bila ada.
     *
     * @return array<string, mixed>|null
     */
    public function readMeta(int $studentId): ?array
    {
        $disk = Storage::disk('local');
        $path = $this->metaPath($studentId);
        if (! $disk->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Periksa apakah siswa sudah memiliki metadata template versi terbaru.
     */
    public function hasCurrentVersion(int $studentId): bool
    {
        $meta = $this->readMeta($studentId);

        return is_array($meta) && ($meta['version'] ?? null) === self::TEMPLATE_VERSION;
    }

    /**
     * Baca array 1434 float dari `template.json` bila tersedia dan panjangnya sah.
     *
     * @return list<float>|null
     */
    public function readVector(int $studentId): ?array
    {
        $disk = Storage::disk('local');
        $path = $this->templatePath($studentId);
        if (! $disk->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) $disk->get($path), true);
        if (! is_array($decoded) || count($decoded) !== self::VECTOR_LENGTH) {
            return null;
        }

        return $decoded;
    }

    public function templatePath(int $studentId): string
    {
        return "face-photos/{$studentId}/template.json";
    }

    public function metaPath(int $studentId): string
    {
        return "face-photos/{$studentId}/template_meta.json";
    }
}
