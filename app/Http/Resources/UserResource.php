<?php

namespace App\Http\Resources;

use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $term = app(\App\Services\AcademicTermService::class)->current();

        return [
            'id' => $this->id,
            'student_number' => $this->student_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'institute' => $this->institute?->name,
            'program' => $this->program?->name,
            'year_level' => $this->year_level,
            'sex' => $this->sex,
            'profile_photo' => $this->profile_photo,
            'academic_term' => $term?->displayName(),
            'is_enrolled' => $this->is_enrolled,
            'role' => app(PermissionRegistry::class)->resolveRoleFor($this->resource)->value,
            'needs_onboarding' => $this->needsOnboarding(),
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
