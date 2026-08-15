<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceBinding extends Model
{
    protected $fillable = [
        'user_id',
        'device_fingerprint',
        'device_meta',
        'bound_at',
    ];

    protected function casts(): array
    {
        return [
            'device_meta' => 'array',
            'bound_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}