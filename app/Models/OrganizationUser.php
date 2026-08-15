<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationUser extends Pivot
{
    protected $table = 'organization_user';

    protected $fillable = [
        'user_id',
        'organization_id',
        'role',
        'position',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'assigned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
