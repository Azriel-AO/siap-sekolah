<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sidik jari: siswa boleh punya maksimal satu ID sidik jari dari sensor.
     * Kolom nullable agar data demo lama (tanpa sidik jari) tetap valid.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('fingerprint_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['fingerprint_id']);
            $table->dropColumn('fingerprint_id');
        });
    }
};
