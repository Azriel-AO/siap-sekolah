<?php

namespace App\Services;

use App\Models\FaceEnrollment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Layanan penyimpanan dan pembaruan foto pendaftaran wajah siswa (all-or-nothing).
 */
class FaceEnrollmentService
{
    public function __construct(
        private readonly PhotoUploadService $photoService,
        private readonly FaceTemplateService $faceTemplates
    ) {}

    /**
     * Validasi dan simpan tiga foto wajah siswa serta perbarui cache template wajah.
     *
     * @param  array<int|string, mixed>  $photos
     * @param  list<float>|null  $sanitizedTemplate
     * @return array{ok: bool, enrollment?: FaceEnrollment, message?: string}
     */
    public function saveEnrollment(Student $student, array $photos, ?array $sanitizedTemplate): array
    {
        $decodedBatch = $this->photoService->decodeBatch($photos);
        if (! $decodedBatch['ok']) {
            $slot = (int) ($decodedBatch['failedSlot'] ?? 1);

            return [
                'ok' => false,
                'message' => "Foto ke-{$slot} tidak sah. Gunakan format JPG/PNG maksimal 2 MB.",
            ];
        }

        $photoItems = $decodedBatch['items'];
        $existing = $student->faceEnrollment;
        $now = Carbon::now('Asia/Jakarta');
        $stamp = $now->format('YmdHis');

        $paths = [];
        foreach ($photoItems as $slot => $item) {
            $paths[$slot] = "face-photos/{$student->id}/face-{$student->id}-{$slot}-{$stamp}.{$item['extension']}";
        }

        $written = [];
        $oldPaths = [];

        try {
            $enrollment = DB::transaction(function () use ($existing, $paths, $photoItems, $student, $now, &$written, &$oldPaths): FaceEnrollment {
                if ($existing) {
                    // Jangan hapus dulu — catat saja. Penghapusan dilakukan SETELAH commit
                    // sukses agar kegagalan di tengah transaksi tidak meninggalkan DB
                    // yang menunjuk ke file lama yang sudah terhapus.
                    foreach ([$existing->photo_1_path, $existing->photo_2_path, $existing->photo_3_path] as $oldPath) {
                        if ($oldPath) {
                            $oldPaths[] = $oldPath;
                        }
                    }
                }

                foreach ($paths as $slot => $path) {
                    if (! Storage::disk('local')->put($path, $photoItems[$slot]['binary'])) {
                        throw new \RuntimeException("Gagal menulis foto wajah ke penyimpanan: {$path}");
                    }

                    $written[] = $path;
                }

                if ($existing) {
                    $existing->update([
                        'photo_1_path' => $paths[1],
                        'photo_2_path' => $paths[2],
                        'photo_3_path' => $paths[3],
                        'consented_at' => $now,
                        'last_reenrolled_at' => $now,
                    ]);

                    return $existing->fresh();
                }

                return FaceEnrollment::create([
                    'student_id' => $student->id,
                    'photo_1_path' => $paths[1],
                    'photo_2_path' => $paths[2],
                    'photo_3_path' => $paths[3],
                    'consented_at' => $now,
                    'last_reenrolled_at' => null,
                ]);
            });
        } catch (\Throwable) {
            foreach ($written as $path) {
                Storage::disk('local')->delete($path);
            }

            return [
                'ok' => false,
                'message' => $existing
                    ? 'Gagal menyimpan pendaftaran ulang. Coba lagi.'
                    : 'Gagal menyimpan pendaftaran. Coba lagi.',
            ];
        }

        // Commit sukses: barulah foto lama dihapus dari disk (lewati bila path sama pada detik yang sama).
        foreach ($oldPaths as $oldPath) {
            if (! in_array($oldPath, $paths, true)) {
                Storage::disk('local')->delete($oldPath);
            }
        }

        Cache::forget(FaceTemplateService::CACHE_KEY);

        if ($sanitizedTemplate !== null) {
            $this->faceTemplates->storeForStudent((int) $student->id, $sanitizedTemplate, 'face_enrollment');
        } else {
            $this->faceTemplates->clearForStudent((int) $student->id);
        }

        return [
            'ok' => true,
            'enrollment' => $enrollment,
        ];
    }
}
