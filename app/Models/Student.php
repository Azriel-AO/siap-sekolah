<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasFactory;

    private ?string $precomputedCardLabel = null;

    protected $fillable = [
        'school_class_id',
        'user_id',
        'nis',
        'name',
        'gender',
        'nisn',
        'induk',
        'fingerprint_id',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rfidCard(): HasOne
    {
        return $this->hasOne(RfidCard::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function faceEnrollment(): HasOne
    {
        return $this->hasOne(FaceEnrollment::class);
    }

    public function izinSakits(): HasMany
    {
        return $this->hasMany(IzinSakit::class);
    }

    /**
     * Isi awal cache label kartu untuk koleksi siswa dalam 1 query agar tidak memicu N+1.
     *
     * @param  Collection<int, Student>  $students
     */
    public static function preloadCardLabels(Collection $students): void
    {
        if ($students->isEmpty()) {
            return;
        }

        $classIds = $students
            ->pluck('school_class_id')
            ->filter()
            ->unique()
            ->values();

        if ($classIds->isEmpty()) {
            return;
        }

        $positionsByClass = self::query()
            ->whereIn('school_class_id', $classIds)
            ->orderBy('id')
            ->get(['id', 'school_class_id'])
            ->groupBy('school_class_id')
            ->map(fn (Collection $group): array => $group->pluck('id')->flip()->all());

        foreach ($students as $student) {
            if (! $student->school_class_id || ! $student->rfidCard || ! $student->schoolClass) {
                $student->precomputedCardLabel = null;

                continue;
            }

            $slugKelas = $student->schoolClass->slug !== null && $student->schoolClass->slug !== ''
                ? strtoupper($student->schoolClass->slug)
                : strtoupper(Str::slug($student->schoolClass->name));

            $posMap = $positionsByClass->get($student->school_class_id, []);
            $posisi = $posMap[$student->id] ?? false;
            $nomorUrut = $posisi !== false ? ((int) $posisi + 1) : 1;

            $student->precomputedCardLabel = sprintf('%s-%03d', $slugKelas, $nomorUrut);
        }
    }

    /**
     * Label kartu cantik berformat "{slug_kelas}-{nomor_urut_3_digit}" (misal: "10-TKJ-1-003").
     * Mengembalikan null apabila siswa belum memiliki kelas atau kartu RFID.
     * UID fisik di tabel rfid_cards tetap mentah untuk pembacaan scanner.
     */
    public function getCardLabelAttribute(): ?string
    {
        if (! $this->school_class_id || ! $this->rfidCard || ! $this->schoolClass) {
            return null;
        }

        if ($this->precomputedCardLabel !== null) {
            return $this->precomputedCardLabel;
        }

        $slugKelas = $this->schoolClass->slug !== null && $this->schoolClass->slug !== ''
            ? strtoupper($this->schoolClass->slug)
            : strtoupper(Str::slug($this->schoolClass->name));

        $ids = self::where('school_class_id', $this->school_class_id)
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $posisi = $ids->search($this->id);
        $nomorUrut = $posisi !== false ? ((int) $posisi + 1) : 1;

        return $this->precomputedCardLabel = sprintf('%s-%03d', $slugKelas, $nomorUrut);
    }

    /**
     * Buat atau perbarui akun login siswa secara otomatis:
     * - email: {NIS}@knbi.sch.id
     * - password: 16 karakter acak yang di-hash (saat akun baru dibuat)
     * - role: 'siswa'
     * - student_id: ID siswa ini
     * Juga menyinkronkan kolom user_id pada tabel students dan mencegah tabrakan akun admin/guru.
     */
    public function syncUserAccount(): User
    {
        $email = $this->nis.'@knbi.sch.id';
        $hasStudentIdColumn = Schema::hasColumn('users', 'student_id');

        $existingWithEmail = User::where('email', $email)->first();
        if ($existingWithEmail && $existingWithEmail->role !== 'siswa') {
            throw new \RuntimeException('Email sudah digunakan oleh akun non-siswa');
        }

        $user = null;
        if ($this->user_id) {
            $user = User::find($this->user_id);
        }
        if (! $user && $hasStudentIdColumn) {
            $user = User::where('student_id', $this->id)->first();
        }

        if ($user) {
            $updateAttributes = [
                'name' => $this->name,
                'email' => $email,
                'role' => 'siswa',
            ];
            if ($hasStudentIdColumn) {
                $updateAttributes['student_id'] = $this->id;
            }
            $user->update($updateAttributes);
        } else {
            $attributes = [
                'name' => $this->name,
                'password' => Hash::make(Str::random(16)),
                'role' => 'siswa',
            ];
            if ($hasStudentIdColumn) {
                $attributes['student_id'] = $this->id;
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                $attributes
            );
        }

        if ((int) $this->user_id !== (int) $user->id) {
            $this->forceFill(['user_id' => $user->id])->saveQuietly();
        }

        return $user;
    }
}
