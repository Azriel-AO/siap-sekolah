<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasFactory;

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
     * Label kartu cantik berformat "{slug_kelas}-{nomor_urut_3_digit}" (misal: "10-TKJ-1-003").
     * Mengembalikan null apabila siswa belum memiliki kelas atau kartu RFID.
     * UID fisik di tabel rfid_cards tetap mentah untuk pembacaan scanner.
     */
    public function getCardLabelAttribute(): ?string
    {
        if (! $this->school_class_id || ! $this->rfidCard || ! $this->schoolClass) {
            return null;
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

        return sprintf('%s-%03d', $slugKelas, $nomorUrut);
    }

    /**
     * Buat atau perbarui akun login siswa secara otomatis:
     * - email: {NIS}@knbi.sch.id
     * - password: Hash::make('password') (saat akun baru dibuat)
     * - role: 'siswa'
     * - student_id: ID siswa ini
     * Juga menyinkronkan kolom user_id pada tabel students.
     */
    public function syncUserAccount(): User
    {
        $email = $this->nis.'@knbi.sch.id';
        $hasStudentIdColumn = Schema::hasColumn('users', 'student_id');

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
                'password' => Hash::make('password'),
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
