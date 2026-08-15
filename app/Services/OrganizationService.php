<?php

namespace App\Services;

use App\Models\Organization;
use App\Repositories\OrganizationRepository;
use Illuminate\Database\Eloquent\Collection;

class OrganizationService
{
    public function __construct(
        private OrganizationRepository $repository
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(int $id): ?Organization
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): Organization
    {
        return $this->repository->create($data);
    }

    public function update(Organization $organization, array $data): Organization
    {
        return $this->repository->update($organization, $data);
    }

    public function delete(Organization $organization): bool
    {
        return $this->repository->delete($organization);
    }

    public function getTree(): Collection
    {
        return $this->repository->getTree();
    }

    public function getByType(string $type): Collection
    {
        return $this->repository->getByType($type);
    }

    public function getAncestors(Organization $organization): array
    {
        $ancestors = [];
        $current = $organization;

        while ($current->parent) {
            $ancestors[] = $current->parent;
            $current = $current->parent;
        }

        return $ancestors;
    }

    public function getDescendantIds(Organization $organization): array
    {
        $ids = [$organization->id];

        foreach ($organization->children as $child) {
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }

        return $ids;
    }
}
