<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom foto bukti absen (PRD F1): foto saat tap masuk & tap pulang.
     * Guard hasColumn agar aman dijalankan berulang (SQLite).
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'photo_in_path')) {
                $table->string('photo_in_path')->nullable();
            }
            if (! Schema::hasColumn('attendances', 'photo_out_path')) {
                $table->string('photo_out_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'photo_out_path')) {
                $table->dropColumn('photo_out_path');
            }
            if (Schema::hasColumn('attendances', 'photo_in_path')) {
                $table->dropColumn('photo_in_path');
            }
        });
    }
};