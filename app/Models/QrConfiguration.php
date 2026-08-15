<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'type',
        'valid_from',
        'valid_until',
        'latitude',
        'longitude',
        'geofence_radius',
        'required_years',
        'qr_data',
        'is_generated',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geofence_radius' => 'integer',
            'required_years' => 'array',
            'is_generated' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
