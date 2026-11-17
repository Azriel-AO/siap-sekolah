<?php

namespace App\Services;

use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Layanan sanitasi, penyimpanan, hidrasi, dan pembacaan vektor template wajah 1434 float (v2 Procrustes IOD).
 */
class FaceTemplateService
{
    public const TEMPLATE_VERSION = 'v2-procrustes-iod';

    public const CACHE_KEY = 'kiosk_face_templates';

    public const VECTOR_LENGTH = 1434;

    /**
     * Ambil seluruh template wajah untuk kiosk gerbang (dari cache atau bangun ulang beserta data hidrasi).
     *
     * @return list<array<string, mixed>>
     */
    public function getTemplatesForKiosk(bool $fresh, PhotoUploadService $photoService): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, 300, function () use ($photoService): array {
            $students = Student::with('faceEnrollment')
                ->has('faceEnrollment')
                ->orderBy('name')
                ->get(['id', 'name']);

            $list = [];
            $disk = Storage::disk('local');

            foreach ($students as $student) {
                $enrollment = $student->faceEnrollment;
                if (! $enrollment) {
                    continue;
                }

                $meta = $this->readMeta((int) $student->id);
                $hasCurrentVersion = is_array($meta) && ($meta['version'] ?? null) === self::TEMPLATE_VERSION;

                if ($hasCurrentVersion) {
                    if (($meta['status'] ?? 'ok') === 'unextractable') {
                        continue;
                    }

                    $storedVector = $this->readVector((int) $student->id);
                    if ($storedVector !== null) {
                        $list[] = [
                            'student_id' => $student->id,
                            'nama' => $student->name,
                            'template' => $storedVector,
                        ];

                        continue;
                    }
                }

                $photos = $photoService->readStudentEnrollmentPhotos($student);
                if (count($photos) === 3) {
                    $list[] = [
                        'student_id' => $student->id,
                        'nama' => $student->name,
                        'template' => null,
                        'needs_extraction' => true,
                        'face_photos' => $photos,
                    ];

                    continue;
                }

                $template = $this->readVector((int) $student->id);
                if ($template === null) {
                    $template = $this->generateDeterministicVector((int) $student->id);
                    try {
                        $disk->put($this->templatePath((int) $student->id), json_encode($template));
                    } catch (\Throwable) {
                        // Abaikan kegagalan penulisan disk; template tetap dikembalikan.
                    }
                }

                $list[] = [
                    'student_id' => $student->id,
                    'nama' => $student->name,
                    'template' => $template,
                ];
            }

            return $list;
        });
    }

    /**
     * Sinkronkan hasil ekstraksi template wajah siswa dari kiosk apabila versi terbaru belum ada.
     *
     * @return array{httpStatus: int, body: array<string, mixed>}
     */
    public function syncForStudent(int $studentId, string $status, mixed $rawTemplate): array
    {
        $student = Student::with('faceEnrollment')->findOrFail($studentId);

        if (! $student->faceEnrollment) {
            return [
                'httpStatus' => 422,
                'body' => [
                    'ok' => false,
                    'message' => 'Siswa belum memiliki pendaftaran wajah.',
                ],
            ];
        }

        if ($this->hasCurrentVersion((int) $student->id)) {
            return [
                'httpStatus' => 200,
                'body' => [
                    'ok' => true,
                    'already_synced' => true,
                ],
            ];
        }

        if ($status === 'unextractable') {
            $this->markUnextractable((int) $student->id, 'kiosk_hydration');

            return [
                'httpStatus' => 200,
                'body' => [
                    'ok' => true,
                    'status' => 'unextractable',
                ],
            ];
        }

        $sanitized = $this->sanitizeVector($rawTemplate);
        if ($sanitized === null) {
            return [
                'httpStatus' => 422,
                'body' => [
                    'ok' => false,
                    'message' => 'Vektor template wajah tidak sah atau wajib berisi tepat 1434 angka berhingga.',
                ],
            ];
        }

        $this->storeForStudent((int) $student->id, $sanitized, 'kiosk_hydration');
        Cache::forget(self::CACHE_KEY);

        return [
            'httpStatus' => 200,
            'body' => [
                'ok' => true,
                'status' => 'ok',
            ],
        ];
    }

    /**
     * Buat vektor template landmark deterministik (1434 float) dengan L2-norm = 1.0.
     *
     * @return list<float>
     */
    public function generateDeterministicVector(int $studentId): array
    {
        $count = self::VECTOR_LENGTH;
        $raw = [];
        $sum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $val = sin($studentId * 7919 + $i * 1.6180339887);
            $raw[] = $val;
            $sum += $val;
        }

        $mean = $sum / $count;
        $sumSquares = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $centered = $raw[$i] - $mean;
            $raw[$i] = $centered;
            $sumSquares += $centered * $centered;
        }

        $norm = sqrt($sumSquares);
        if ($norm > 1e-8) {
            for ($i = 0; $i < $count; $i++) {
                $raw[$i] = (float) round($raw[$i] / $norm, 6);
            }
        }

        return $raw;
    }

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
