<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konsolidasikan kolom warisan `nis` ke kolom utama `nisn` (NOT NULL & UNIQUE) secara non-destruktif.
     */
    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        $hasNis = Schema::hasColumn('students', 'nis');
        $hasNisn = Schema::hasColumn('students', 'nisn');

        if ($hasNis && ! $hasNisn) {
            Schema::table('students', function (Blueprint $table): void {
                $table->renameColumn('nis', 'nisn');
            });

            return;
        }

        if ($hasNis && $hasNisn) {
            $missingNisnRows = DB::table('students')
                ->select(['id', 'nis', 'nisn'])
                ->where(function ($query): void {
                    $query->whereNull('nisn')->orWhere('nisn', '');
                })
                ->get();

            foreach ($missingNisnRows as $row) {
                $candidate = trim((string) $row->nis);
                if ($candidate === '') {
                    $candidate = '0000000000'.$row->id;
                }

                if (DB::table('students')->where('nisn', $candidate)->where('id', '!=', $row->id)->exists()) {
                    $candidate = substr($candidate, 0, 14).'-'.$row->id;
                }

                DB::table('students')->where('id', $row->id)->update(['nisn' => $candidate]);
            }

            $mismatchRows = DB::table('students')
                ->select(['id', 'nis', 'nisn', 'user_id'])
                ->whereNotNull('user_id')
                ->whereNotNull('nisn')
                ->whereColumn('nis', '!=', 'nisn')
                ->get();

            foreach ($mismatchRows as $row) {
                $oldEmail = trim((string) $row->nis).'@knbi.sch.id';
                $newEmail = trim((string) $row->nisn).'@knbi.sch.id';

                $user = DB::table('users')->where('id', $row->user_id)->first();
                if (! $user || $user->role !== 'siswa' || $user->email !== $oldEmail) {
                    continue;
                }

                $emailTaken = DB::table('users')
                    ->where('email', $newEmail)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if (! $emailTaken) {
                    DB::table('users')->where('id', $user->id)->update(['email' => $newEmail]);
                }
            }

            $nisIndexes = array_values(array_filter(
                Schema::getIndexes('students'),
                static fn (array $index): bool => in_array('nis', $index['columns'] ?? [], true)
            ));

            if ($nisIndexes !== []) {
                Schema::table('students', function (Blueprint $table) use ($nisIndexes): void {
                    foreach ($nisIndexes as $index) {
                        if (! empty($index['unique'])) {
                            $table->dropUnique($index['name']);
                        } else {
                            $table->dropIndex($index['name']);
                        }
                    }
                });
            }

            Schema::table('students', function (Blueprint $table): void {
                $table->dropColumn('nis');
            });
        }

        if (Schema::hasColumn('students', 'nisn')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->string('nisn', 20)->nullable(false)->change();
            });

            if (! Schema::hasIndex('students', ['nisn'], 'unique') && ! Schema::hasIndex('students', 'students_nisn_unique')) {
                Schema::table('students', function (Blueprint $table): void {
                    $table->unique('nisn', 'students_nisn_unique');
                });
            }
        }
    }

    /**
     * Kembalikan kolom `nis` dari `nisn` untuk rollback non-destruktif.
     */
    public function down(): void
    {
        if (! Schema::hasTable('students') || ! Schema::hasColumn('students', 'nisn')) {
            return;
        }

        if (! Schema::hasColumn('students', 'nis')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->string('nis', 20)->nullable()->after('user_id');
            });

            DB::table('students')->update(['nis' => DB::raw('nisn')]);

            Schema::table('students', function (Blueprint $table): void {
                $table->string('nis', 20)->nullable(false)->change();
                $table->unique('nis', 'students_nis_unique');
                $table->string('nisn', 20)->nullable()->change();
            });
        }
    }
};
