<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'qr_configuration_id',
        'user_id',
        'academic_term_id',
        'scanned_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function qrConfiguration(): BelongsTo
    {
        return $this->belongsTo(QrConfiguration::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function event(): HasOneThrough
    {
        return $this->hasOneThrough(
            Event::class,
            QrConfiguration::class,
            'id',
            'id',
            'qr_configuration_id',
            'event_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
