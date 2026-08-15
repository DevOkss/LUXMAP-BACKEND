<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'current_institute_id',
        'current_program_id',
        'requested_institute_id',
        'requested_program_id',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentInstitute(): BelongsTo
    {
        return $this->belongsTo(Institute::class, 'current_institute_id');
    }

    public function currentProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'current_program_id');
    }

    public function requestedInstitute(): BelongsTo
    {
        return $this->belongsTo(Institute::class, 'requested_institute_id');
    }

    public function requestedProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'requested_program_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}