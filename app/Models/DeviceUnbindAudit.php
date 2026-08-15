<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceUnbindAudit extends Model
{
    protected $fillable = [
        'user_id',
        'previous_device_fingerprint',
        'reason',
        'unbound_by',
        'unbound_at',
    ];

    protected function casts(): array
    {
        return [
            'unbound_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unboundBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unbound_by');
    }
}