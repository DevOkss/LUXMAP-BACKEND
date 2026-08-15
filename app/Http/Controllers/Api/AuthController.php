<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Services\AuthService;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private WorkspaceService $workspaceService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->attempt(
            $request->validated(),
            $request->header('X-Device-Fingerprint')
        );

        return response()->json([
            'token' => $result['token'],
            'user' => UserResource::make($result['user']),
            'workspaces' => $result['workspaces'],
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            $request->validated(),
            $request->header('X-Device-Fingerprint')
        );

        return response()->json([
            'token' => $result['token'],
            'user' => UserResource::make($result['user']),
            'workspaces' => $result['workspaces'],
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function refresh(Request $request): JsonResponse
    {
        $result = $this->authService->refreshUser($request->user());

        return response()->json([
            'user' => UserResource::make($result['user']),
            'synced' => $result['synced'],
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $permissions = $this->workspaceService->resolvePermissions($user);

        return response()->json([
            'user' => UserResource::make($user),
            'permissions' => $permissions,
        ]);
    }

    public function workspaces(Request $request): JsonResponse
    {
        $workspaces = $this->workspaceService->getAvailableWorkspaces($request->user());

        return response()->json(['workspaces' => $workspaces]);
    }

    public function switchWorkspace(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();
        $permissions = $this->workspaceService->resolvePermissions($user, $organization);

        return response()->json([
            'workspace' => [
                'id' => "org_{$organization->id}",
                'name' => $organization->name,
                'type' => $organization->type,
                'organization_id' => $organization->id,
            ],
            'permissions' => $permissions,
        ]);
    }
}
