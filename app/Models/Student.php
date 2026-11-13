<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
}
