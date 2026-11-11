<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NIS Induk sekolah (nullable, unik) — berbeda dari NISN.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('induk', 20)->nullable()->unique()->after('nisn');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['induk']);
            $table->dropColumn('induk');
        });
    }
};
