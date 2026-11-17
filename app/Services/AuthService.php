<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private WorkspaceService $workspaceService
    ) {}

    public function attempt(array $credentials, ?string $deviceFingerprint = null): array
    {
        $user = User::where('student_number', $credentials['student_number'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'student_number' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'student_number' => ['Super admins must sign in via the Admin Portal.'],
            ]);
        }

        if (! $user->is_enrolled) {
            throw ValidationException::withMessages([
                'student_number' => ['This account is not enrolled.'],
            ]);
        }

        $token = $this->issueToken($user, $credentials['device_name'] ?? 'soms-api', $deviceFingerprint);

        $workspaces = $this->workspaceService->getAvailableWorkspaces($user);

        return [
            'token' => $token,
            'user' => $user,
            'workspaces' => $workspaces,
        ];
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): User
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
        ]);

        return $user->fresh();
    }

    /**
     * Create an API token for the user. When a device fingerprint is supplied,
     * any previous token issued to that same device is revoked so each device
     * holds at most one active session.
     */
    private function issueToken(User $user, string $name, ?string $deviceFingerprint = null): string
    {
        if ($deviceFingerprint) {
            $user->tokens()
                ->where('device_fingerprint', $deviceFingerprint)
                ->delete();
        }

        $token = $user->createToken($name);

        if ($deviceFingerprint) {
            $token->accessToken->forceFill(['device_fingerprint' => $deviceFingerprint])->save();
        }

        return $token->plainTextToken;
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
