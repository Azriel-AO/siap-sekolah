<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'school_class_id',
        'date',
        'check_in_at',
        'check_out_at',
        'status',
        'last_tap_at',
        'kiosk_device',
        'photo_in_path',
        'photo_out_path',
    ];

    protected function casts(): array
    {
        return [
            // Cast 'date:Y-m-d' agar tanggal tersimpan persis 'YYYY-MM-DD'.
            // Cast 'date' biasa menyimpan 'YYYY-MM-DD 00:00:00' di SQLite,
            // sehingga pencarian kehadiran per hari selalu gagal dicocokkan.
            'date' => 'date:Y-m-d',
            'last_tap_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
