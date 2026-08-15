<?php

namespace App\Services;

use App\Exceptions\DeviceBindingException;
use App\Models\DeviceBinding;
use App\Models\DeviceUnbindAudit;
use App\Models\User;
use App\Models\DeviceTransferRequest;

class DeviceBindingService
{
    /**
     * The device fingerprint header the PWA sends on every device request.
     */
    public const FINGERPRINT_HEADER = 'X-Device-Fingerprint';

    public function bindingFor(User $user): ?DeviceBinding
    {
        return DeviceBinding::where('user_id', $user->id)->first();
    }

    public function isBound(User $user): bool
    {
        return $this->bindingFor($user) !== null;
    }

    /**
     * Idempotently bind a device to the account. Throws a conconflict when the
     * account is already bound to a different device.
     */
    public function bindDevice(User $user, string $fingerprint, array $meta = []): DeviceBinding
    {
        $binding = $this->bindingFor($user);

        if ($binding) {
            if ($binding->device_fingerprint === $fingerprint) {
                return $binding;
            }

            throw new DeviceBindingException(
                'This account is already bound to another device. Transfer the binding from the other device first.'
            );
        }

        return DeviceBinding::create([
            'user_id' => $user->id,
            'device_fingerprint' => $fingerprint,
            'device_meta' => $meta,
            'bound_at' => now(),
        ]);
    }

    /**
     * Request to move the account's binding to the calling device. The current
     * bound device must approve afterwards.
     */
    public function requestTransfer(User $user, string $fingerprint, array $meta = []): DeviceTransferRequest
    {
        $binding = $this->bindingFor($user);

        if (! $binding) {
            // Nothing to transfer — the account simply binds to this device.
            $this->bindDevice($user, $fingerprint, $meta);
            throw new DeviceBindingException('This account was not bound to a device. It is now bound to this device.', 200);
        }

        if ($binding->device_fingerprint === $fingerprint) {
            throw new DeviceBindingException('This device is already bound to this account.', 409);
        }

        return DeviceTransferRequest::create([
            'user_id' => $user->id,
            'requesting_fingerprint' => $fingerprint,
            'requesting_meta' => $meta,
            'status' => DeviceTransferRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);
    }

    /**
     * Only the currently-bound device may approve a pending transfer.
     */
    public function approveTransfer(User $user, DeviceTransferRequest $request, string $decidingFingerprint): DeviceTransferRequest
    {
        $this->assertManageable($user, $request, $decidingFingerprint);

        if ($request->status !== DeviceTransferRequest::STATUS_PENDING) {
            throw new DeviceBindingException('This transfer request has already been settled.', 422);
        }

        $binding = $this->bindingFor($user);
        $previousFingerprint = $binding->device_fingerprint;

        $binding->update([
            'device_fingerprint' => $request->requesting_fingerprint,
            'device_meta' => $request->requesting_meta,
            'bound_at' => now(),
        ]);

        $request->update([
            'status' => DeviceTransferRequest::STATUS_APPROVED,
            'decided_at' => now(),
            'decided_by_fingerprint' => $decidingFingerprint,
        ]);

        // One active session per account: the device whose binding was moved
        // away is logged out immediately so it can no longer use the app.
        $this->revokeDeviceTokens($user, $previousFingerprint);

        return $request;
    }

    public function rejectTransfer(User $user, DeviceTransferRequest $request, string $decidingFingerprint): DeviceTransferRequest
    {
        $this->assertManageable($user, $request, $decidingFingerprint);

        if ($request->status !== DeviceTransferRequest::STATUS_PENDING) {
            throw new DeviceBindingException('This transfer request has already been handled.', 422);
        }

        $request->update([
            'status' => DeviceTransferRequest::STATUS_REJECTED,
            'decided_at' => now(),
            'decided_by_fingerprint' => $decidingFingerprint,
        ]);

        return $request;
    }

    /**
     * Remove the user's device binding (admin/manual unbind). The face
     * enrollment is intentionally kept so the target can re-use on a new
     * device without re-enrolling.
     */
    public function unbind(User $user, string $reason, ?User $unboundBy = null): void
    {
        $binding = $this->bindingFor($user);

        if (! $binding) {
            return;
        }

        DeviceUnbindAudit::create([
            'user_id' => $user->id,
            'previous_device_fingerprint' => $binding->device_fingerprint,
            'reason' => $reason,
            'unbound_by' => $unboundBy?->id,
            'unbound_at' => now(),
        ]);

        DeviceTransferRequest::where('user_id', $user->id)
            ->where('status', DeviceTransferRequest::STATUS_PENDING)
            ->delete();

        $fingerprint = $binding->device_fingerprint;
        $binding->delete();

        // The unbound device's session is no longer valid.
        $this->revokeDeviceTokens($user, $fingerprint);
    }

    /**
     * Delete every API token issued to the given device fingerprint, forcing
     * that device to sign in again before it can do anything.
     */
    private function revokeDeviceTokens(User $user, ?string $fingerprint): void
    {
        if (! $fingerprint) {
            return;
        }

        $user->tokens()
            ->where('device_fingerprint', $fingerprint)
            ->delete();
    }

    private function assertManageable(User $user, DeviceTransferRequest $request, string $decidingFingerprint): void
    {
        if ((int) $request->user_id !== $user->id) {
            throw new DeviceBindingException('Transfer request not found.', 404);
        }

        $binding = $this->bindingFor($user);

        if (! $binding) {
            throw new DeviceBindingException('No device is currently bound to this account.', 409);
        }

        if ($binding->device_fingerprint !== $decidingFingerprint) {
            throw new DeviceBindingException(
                'Only the device currently bound to this account can decide this transfer.',
                403
            );
        }

        if ($binding->device_fingerprint === $request->requesting_fingerprint) {
            throw new DeviceBindingException('A device cannot approve its own transfer request.', 422);
        }
    }
}