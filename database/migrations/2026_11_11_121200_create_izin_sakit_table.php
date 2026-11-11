<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan izin/sakit per siswa per tanggal.
     * UNIQUE(student_id, tanggal) — input ulang pada hari yang sama menimpa.
     */
    public function up(): void
    {
        Schema::create('izin_sakit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('status', 10); // izin | sakit
            $table->text('keterangan')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin_sakit');
    }
};
