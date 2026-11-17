<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gabungkan seluruh kelas duplikat bertypo "PS" ke kelas resmi "PSY" (Perbankan Syariah)
     * tanpa kehilangan data siswa, riwayat absensi, maupun informasi wali kelas.
     */
    public function up(): void
    {
        if (! Schema::hasTable('school_classes')) {
            return;
        }

        DB::transaction(function (): void {
            $now = Carbon::now('Asia/Jakarta');
            $classes = DB::table('school_classes')->orderBy('id')->get();

            foreach ($classes as $psClass) {
                $isLegacyPs = preg_match('/\bPS\b/i', (string) $psClass->name) === 1
                    || preg_match('/(^|-)PS(-|$)/i', (string) $psClass->slug) === 1;

                if (! $isLegacyPs) {
                    continue;
                }

                $targetName = trim((string) preg_replace('/\bPS\b/i', 'PSY', (string) $psClass->name));
                $targetSlug = trim((string) preg_replace('/(^|-)PS(-|$)/i', '$1PSY$2', (string) $psClass->slug));

                $target = DB::table('school_classes')
                    ->where('id', '!=', $psClass->id)
                    ->where(function ($query) use ($targetName, $targetSlug): void {
                        $query->where('name', $targetName)
                            ->orWhere('slug', $targetSlug);
                    })
                    ->first();

                if ($target) {
                    if (Schema::hasTable('students')) {
                        DB::table('students')
                            ->where('school_class_id', $psClass->id)
                            ->update([
                                'school_class_id' => $target->id,
                                'updated_at' => $now,
                            ]);
                    }

                    if (Schema::hasTable('attendances')) {
                        DB::table('attendances')
                            ->where('school_class_id', $psClass->id)
                            ->update([
                                'school_class_id' => $target->id,
                                'updated_at' => $now,
                            ]);
                    }

                    if (($target->wali_kelas === null || trim((string) $target->wali_kelas) === '')
                        && $psClass->wali_kelas !== null
                        && trim((string) $psClass->wali_kelas) !== ''
                    ) {
                        DB::table('school_classes')
                            ->where('id', $target->id)
                            ->update([
                                'wali_kelas' => $psClass->wali_kelas,
                                'updated_at' => $now,
                            ]);
                    }

                    $remainingStudents = Schema::hasTable('students')
                        ? DB::table('students')->where('school_class_id', $psClass->id)->count()
                        : 0;
                    $remainingAttendances = Schema::hasTable('attendances')
                        ? DB::table('attendances')->where('school_class_id', $psClass->id)->count()
                        : 0;

                    if ($remainingStudents > 0 || $remainingAttendances > 0) {
                        throw new RuntimeException("Gagal memindahkan seluruh relasi dari kelas {$psClass->name} (ID {$psClass->id}).");
                    }

                    DB::table('school_classes')->where('id', $psClass->id)->delete();
                } else {
                    DB::table('school_classes')
                        ->where('id', $psClass->id)
                        ->update([
                            'name' => $targetName,
                            'slug' => $targetSlug,
                            'department' => trim((string) $psClass->department) !== '' ? $psClass->department : 'Perbankan Syariah',
                            'updated_at' => $now,
                        ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data merge PS -> PSY bersifat permanen agar tidak memunculkan kembali duplikasi kelas.
    }
};
