<?php

namespace App\Models;

use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'institute_id',
        'program_id',
        'name',
        'code',
        'type',
        'description',
        'config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'config' => 'json',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'position', 'assigned_at'])
            ->withTimestamps();
    }

    public function scopeSsc($query)
    {
        return $query->where('type', OrganizationType::SSC);
    }

    public function scopeIsc($query)
    {
        return $query->where('type', OrganizationType::ISC);
    }

    public function scopeSro($query)
    {
        return $query->where('type', OrganizationType::SRO);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForInstitute($query, int $instituteId)
    {
        return $query->where('type', OrganizationType::ISC)
            ->where('institute_id', $instituteId);
    }

    public function scopeForProgram($query, int $programId)
    {
        return $query->where('type', OrganizationType::SRO)
            ->where('program_id', $programId);
    }
}
