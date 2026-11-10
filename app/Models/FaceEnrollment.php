<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'photo_1_path',
        'photo_2_path',
        'photo_3_path',
        'consented_at',
        'last_reenrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'last_reenrolled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
