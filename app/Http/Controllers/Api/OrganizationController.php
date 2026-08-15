<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationService $organizationService
    ) {}

    public function index(Request $request)
    {
        $organizations = $request->boolean('tree')
            ? $this->organizationService->getTree()
            : $this->organizationService->list();

        return OrganizationResource::collection($organizations);
    }

    public function store(OrganizationRequest $request)
    {
        $organization = $this->organizationService->create($request->validated());

        return OrganizationResource::make($organization)->response()->setStatusCode(201);
    }

    public function show(Organization $organization)
    {
        $organization->load(['parent', 'children']);

        return OrganizationResource::make($organization);
    }

    public function update(OrganizationRequest $request, Organization $organization)
    {
        $organization = $this->organizationService->update($organization, $request->validated());

        return OrganizationResource::make($organization);
    }

    public function destroy(Organization $organization)
    {
        $this->organizationService->delete($organization);

        return response()->json(null, 204);
    }
}

