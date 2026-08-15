<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenaltyFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'amount',
        'effective_at',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    /**
     * The current penalty amount for an organization (its latest record),
     * falling back to the global default (organization_id null).
     */
    public static function currentAmountFor(?int $organizationId): int|float
    {
        if ($organizationId) {
            $row = static::where('organization_id', $organizationId)
                ->orderByDesc('effective_at')
                ->orderByDesc('id')
                ->first();

            if ($row) {
                return (float) $row->amount;
            }
        }

        $default = static::whereNull('organization_id')
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        return $default ? (float) $default->amount : 0.0;
    }
}