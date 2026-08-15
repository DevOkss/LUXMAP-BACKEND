<?php

namespace App\Repositories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;

class OrganizationRepository
{
    public function all(): Collection
    {
        return Organization::with('parent')->orderBy('name')->get();
    }

    public function findById(int $id): ?Organization
    {
        return Organization::with(['parent', 'children'])->find($id);
    }

    public function findByCode(string $code): ?Organization
    {
        return Organization::where('code', $code)->first();
    }

    public function create(array $data): Organization
    {
        return Organization::create($data);
    }

    public function update(Organization $organization, array $data): Organization
    {
        $organization->update($data);
        return $organization->fresh();
    }

    public function delete(Organization $organization): bool
    {
        return $organization->delete();
    }

    public function getTree(): Collection
    {
        return Organization::with('children.children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    }

    public function getByType(string $type): Collection
    {
        return Organization::where('type', $type)->orderBy('name')->get();
    }

    public function getActive(): Collection
    {
        return Organization::where('is_active', true)->orderBy('name')->get();
    }

    public function findSsc(): ?Organization
    {
        return Organization::where('type', 'ssc')->first();
    }

    public function findIsc(string $institute): ?Organization
    {
        return Organization::forInstitute($institute)->first();
    }

    public function findSro(string $program): ?Organization
    {
        return Organization::forProgram($program)->first();
    }
}
