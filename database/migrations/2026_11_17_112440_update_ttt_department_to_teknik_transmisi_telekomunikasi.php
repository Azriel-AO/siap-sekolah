<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CORRECT_DEPARTMENT = 'Teknik Transmisi Telekomunikasi';

    private const LEGACY_DEPARTMENT = 'Teknik Tata Udara & Pendingin';

    /**
     * Perbarui nama jurusan kelas TTT dari "Teknik Tata Udara & Pendingin"
     * menjadi "Teknik Transmisi Telekomunikasi" secara idempotent tanpa mengubah ID/relasi.
     */
    public function up(): void
    {
        if (! Schema::hasTable('school_classes')) {
            return;
        }

        DB::transaction(function (): void {
            $now = Carbon::now('Asia/Jakarta');

            DB::table('school_classes')
                ->where(function ($query): void {
                    $query->where('name', 'like', '% TTT %')
                        ->orWhere('slug', 'like', '%-TTT-%')
                        ->orWhere('department', self::LEGACY_DEPARTMENT);
                })
                ->update([
                    'department' => self::CORRECT_DEPARTMENT,
                    'updated_at' => $now,
                ]);
        });
    }

    /**
     * Kembalikan nama jurusan lama hanya untuk kelas TTT apabila dilakukan rollback.
     */
    public function down(): void
    {
        if (! Schema::hasTable('school_classes')) {
            return;
        }

        DB::transaction(function (): void {
            $now = Carbon::now('Asia/Jakarta');

            DB::table('school_classes')
                ->where('department', self::CORRECT_DEPARTMENT)
                ->where(function ($query): void {
                    $query->where('name', 'like', '% TTT %')
                        ->orWhere('slug', 'like', '%-TTT-%');
                })
                ->update([
                    'department' => self::LEGACY_DEPARTMENT,
                    'updated_at' => $now,
                ]);
        });
    }
};
