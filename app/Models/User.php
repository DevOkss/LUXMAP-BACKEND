<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'student_number',
        'name',
        'email',
        'password',
        'institution_password_enc',
        'phone',
        'year_level',
        'sex',
        'profile_photo',
        'is_enrolled',
        'institute_id',
        'program_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'year_level' => 'integer',
            'is_enrolled' => 'boolean',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function shiftRequests(): HasMany
    {
        return $this->hasMany(ShiftRequest::class);
    }

    /**
     * The student's enrollment record for the given academic term.
     */
    public function enrollmentForTerm(AcademicTerm $term): ?StudentEnrollment
    {
        return $this->studentEnrollments()
            ->where('academic_term_id', $term->id)
            ->first();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'position', 'assigned_at'])
            ->withTimestamps();
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function deviceBinding(): HasOne
    {
        return $this->hasOne(DeviceBinding::class);
    }

    public function faceEnrollment(): HasOne
    {
        return $this->hasOne(FaceEnrollment::class);
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SUPER_ADMIN);
    }

    public function hasRole(UserRole|array|string $roles): bool
    {
        $roles = collect(is_array($roles) ? $roles : [$roles])
            ->map(fn ($role) => $role instanceof UserRole ? $role->value : (string) $role)
            ->all();

        return $this->organizations()
            ->wherePivotIn('role', $roles)
            ->exists();
    }

    /**
     * The role the user holds in the given organization, if any.
     */
    public function roleInOrganization(Organization $organization): ?UserRole
    {
        $pivot = $this->organizations()
            ->where('organization_id', $organization->id)
            ->first()?->pivot;

        return $pivot?->role;
    }

    /**
     * True when the user holds any admin-capable role (portal login allowed).
     */
    public function hasAdminPortalRole(): bool
    {
        return $this->hasRole(UserRole::adminPortalRoles());
    }

    public function needsOnboarding(): bool
    {
        $enrollment = $this->currentEnrollment();

        if (!$enrollment) {
            // No active academic term: fall back to the legacy column check.
            return is_null($this->institute_id) || is_null($this->program_id);
        }

        return is_null($enrollment->institute_id) || is_null($enrollment->program_id);
    }

    /**
     * The enrollment snapshot for the currently-active academic term.
     */
    public function currentEnrollment(): ?StudentEnrollment
    {
        $term = app(\App\Services\AcademicTermService::class)->current();

        return $term ? $this->enrollmentForTerm($term) : null;
    }

    public function hasOfficerRole(): bool
    {
        return $this->hasRole(UserRole::officerRoles());
    }

    public function hasStaffRole(): bool
    {
        return $this->hasRole(UserRole::staffRoles());
    }
}
