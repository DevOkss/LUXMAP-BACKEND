<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'academic_term_id',
        'institute_id',
        'program_id',
        'year_level',
        'is_enrolled',
    ];

    protected function casts(): array
    {
        return [
            'year_level' => 'integer',
            'is_enrolled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}