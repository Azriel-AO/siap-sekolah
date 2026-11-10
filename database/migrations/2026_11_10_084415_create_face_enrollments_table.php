<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('face_enrollments', function (Blueprint $table) {
            $table->id();
            // Satu siswa maksimal satu pendaftaran wajah (FR-006)
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            // Path relatif file foto di disk privat 'local' (folder face-photos/)
            $table->string('photo_1_path', 120);
            $table->string('photo_2_path', 120);
            $table->string('photo_3_path', 120);
            // Waktu server saat siswa menyetujui pernyataan izin orang tua/wali (FR-012)
            $table->timestamp('consented_at');
            // Waktu server daftar ulang terakhir; null = belum pernah daftar ulang (FR-014)
            $table->timestamp('last_reenrolled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('face_enrollments');
    }
};
