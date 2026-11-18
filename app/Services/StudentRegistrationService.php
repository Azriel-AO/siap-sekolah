<?php

namespace App\Services;

use App\Models\FaceEnrollment;
use App\Models\RfidCard;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Layanan transaksional pendaftaran siswa baru (biodata, akun login, kartu RFID, biometrik).
 */
class StudentRegistrationService
{
    public function __construct(
        private readonly PhotoUploadService $photoService,
        private readonly FaceTemplateService $faceTemplates
    ) {}

    /**
     * Daftarkan siswa baru beserta seluruh relasi dan file biometrik secara atomik.
     *
     * @param  array<string, mixed>  $validated
     */
    public function register(array $validated): Student
    {
        $cardUid = trim((string) $validated['card_uid']);
        $kartuTerpakai = RfidCard::with('student')->where('card_uid', $cardUid)->first();
        if ($kartuTerpakai && $kartuTerpakai->student) {
            throw ValidationException::withMessages([
                'card_uid' => 'Kartu dengan UID tersebut sudah terdaftar. Gunakan kartu lain atau hubungi admin.',
            ]);
        }

        $decodedBatch = $this->photoService->decodeBatch($validated['photos']);
        if (! $decodedBatch['ok']) {
            $slot = (int) ($decodedBatch['failedSlot'] ?? 1);

            throw ValidationException::withMessages([
                'photos.'.($slot - 1) => 'Foto ke-'.$slot.' tidak sah. Gunakan format JPG/PNG maksimal 2 MB.',
            ]);
        }

        $photoItems = $decodedBatch['items'];
        $now = Carbon::now('Asia/Jakarta');
        $fingerprintId = trim((string) ($validated['fingerprint_id'] ?? '')) ?: null;
        $sanitizedTemplate = $this->faceTemplates->sanitizeVector($validated['face_template'] ?? null);
        $writtenPaths = [];

        try {
            $student = DB::transaction(function () use (
                $validated,
                $cardUid,
                $kartuTerpakai,
                $photoItems,
                $now,
                $fingerprintId,
                $sanitizedTemplate,
                &$writtenPaths
            ): Student {
                $siswa = Student::create([
                    'school_class_id' => $validated['school_class_id'],
                    // Siswa baru belum punya NIS dari sekolah; pakai NISN sementara.
                    'nis' => $validated['nisn'],
                    'nisn' => $validated['nisn'],
                    'name' => $validated['name'],
                    'gender' => $validated['gender'],
                    'fingerprint_id' => $fingerprintId,
                ]);

                $siswa->syncUserAccount();

                if ($kartuTerpakai) {
                    $kartuTerpakai->update(['student_id' => $siswa->id, 'is_active' => true]);
                } else {
                    RfidCard::create(['student_id' => $siswa->id, 'card_uid' => $cardUid, 'is_active' => true]);
                }

                $photoPaths = [];
                foreach ($photoItems as $slot => $item) {
                    $path = "face-photos/{$siswa->id}/face-{$siswa->id}-{$slot}-{$now->format('YmdHis')}.{$item['extension']}";

                    if (! Storage::disk('local')->put($path, $item['binary'])) {
                        throw new \RuntimeException("Gagal menulis foto wajah ke penyimpanan: {$path}");
                    }

                    $photoPaths[$slot] = $path;
                    $writtenPaths[] = $path;
                }

                if ($sanitizedTemplate !== null) {
                    foreach ($this->faceTemplates->storeForStudent($siswa->id, $sanitizedTemplate, 'pendaftaran') as $tplPath) {
                        $writtenPaths[] = $tplPath;
                    }
                }

                FaceEnrollment::create([
                    'student_id' => $siswa->id,
                    'photo_1_path' => $photoPaths[1],
                    'photo_2_path' => $photoPaths[2],
                    'photo_3_path' => $photoPaths[3],
                    'consented_at' => $now,
                    'last_reenrolled_at' => null,
                ]);

                return $siswa;
            });
        } catch (\Throwable $e) {
            foreach ($writtenPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        } finally {
            try {
                Cache::forget(FaceTemplateService::CACHE_KEY);
                Cache::forget("pendaftaran_{$validated['nisn']}");
            } catch (\Throwable) {
                // Abaikan kegagalan cache agar tidak menghapus foto yang sudah berhasil di-commit.
            }
        }

        return $student;
    }
}
