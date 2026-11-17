<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar index performa pada kolom yang sering di-query.
     *
     * @var array<string, array<string, string>>
     */
    private const INDEXES = [
        'students' => [
            'nisn' => 'students_nisn_index',
            'school_class_id' => 'students_school_class_id_index',
        ],
        'attendances' => [
            'student_id' => 'attendances_student_id_index',
            'date' => 'attendances_date_index',
            'school_class_id' => 'attendances_school_class_id_index',
        ],
        'users' => [
            'email' => 'users_email_index',
            'student_id' => 'users_student_id_index',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $toAdd = [];
            foreach ($columns as $column => $indexName) {
                if (Schema::hasColumn($tableName, $column)
                    && ! Schema::hasIndex($tableName, [$column])
                    && ! Schema::hasIndex($tableName, $indexName)) {
                    $toAdd[$column] = $indexName;
                }
            }

            if ($toAdd === []) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($toAdd): void {
                foreach ($toAdd as $column => $indexName) {
                    $table->index($column, $indexName);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $toDrop = [];
            foreach ($columns as $indexName) {
                if (Schema::hasIndex($tableName, $indexName)) {
                    $toDrop[] = $indexName;
                }
            }

            if ($toDrop === []) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($toDrop): void {
                foreach ($toDrop as $indexName) {
                    $table->dropIndex($indexName);
                }
            });
        }
    }
};
