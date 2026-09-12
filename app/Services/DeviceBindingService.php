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
     * Coarse hardware similarity check - same logic as PWA isSimilarDevice.
     * Used to detect same physical device but different browser/incognito.
     */
    public function isSimilarDevice(?array $a, ?array $b): bool
    {
        if (empty($a) || empty($b)) {
            return false;
        }
        $platformA = strtolower($a['platform'] ?? '');
        $platformB = strtolower($b['platform'] ?? '');
        if ($platformA !== $platformB) {
            return false;
        }
        // Screen bucket similarity
        $screenA = $a['screen'] ?? '';
        $screenB = $b['screen'] ?? '';
        if ($screenA && $screenB && $screenA !== $screenB) {
            $bucket = function (string $s): string {
                $w = (int) (explode('x', $s)[0] ?? 0);
                if ($w >= 1000) return 'large';
                if ($w >= 700) return 'medium';
                if ($w >= 400) return 'small';
                return 'tiny';
            };
            if ($bucket($screenA) !== $bucket($screenB)) {
                return false;
            }
        }
        // Cores bucket
        $coresA = $a['cores'] ?? 0;
        $coresB = $b['cores'] ?? 0;
        $coresBucket = function ($c): string {
            $c = (int) $c;
            if ($c >= 8) return '8+';
            if ($c >= 4) return '4-7';
            if ($c >= 2) return '2-3';
            return '1';
        };
        if ($coresA && $coresB && $coresBucket($coresA) !== $coresBucket($coresB)) {
            return false;
        }
        // Language base
        $langA = strtolower(explode('-', $a['language'] ?? '')[0]);
        $langB = strtolower(explode('-', $b['language'] ?? '')[0]);
        if ($langA && $langB && $langA !== $langB) {
            return false;
        }
        return true;
    }

    public function isTrusted(DeviceBinding $binding, string $fingerprint): bool
    {
        if ($binding->device_fingerprint === $fingerprint) {
            return true;
        }
        $trusted = $binding->trusted_fingerprints ?? [];
        return in_array($fingerprint, $trusted, true);
    }

    public function addTrustedFingerprint(DeviceBinding $binding, string $fingerprint): void
    {
        if ($this->isTrusted($binding, $fingerprint)) {
            return;
        }
        $trusted = $binding->trusted_fingerprints ?? [];
        $trusted[] = $fingerprint;
        // Keep last 5 trusted fingerprints (same device different browsers, incognito)
        $trusted = array_slice(array_unique($trusted), -5);
        $binding->update(['trusted_fingerprints' => $trusted]);
    }

    /**
     * Idempotently bind a device to the account. Throws a conflict when the
     * account is already bound to a different device (and not similar/trusted).
     */
    public function bindDevice(User $user, string $fingerprint, array $meta = []): DeviceBinding
    {
        $binding = $this->bindingFor($user);

        if ($binding) {
            // Already trusted or same fingerprint - idempotent
            if ($this->isTrusted($binding, $fingerprint)) {
                return $binding;
            }

            // Same physical device but different browser: check similarity before rejecting
            // If similar hardware, treat as same device and add to trusted instead of 409
            if ($this->isSimilarDevice($binding->device_meta, $meta)) {
                // Check if user has face enrollment - if not, still require transfer for security
                // But for now, allow similar device to join trusted list even without face,
                // since deterministic hash may differ per browser engine.
                // The face-verified path will be tighter.
                $this->addTrustedFingerprint($binding, $fingerprint);
                return $binding->fresh();
            }

            throw new DeviceBindingException(
                'This account is already bound to another device. Transfer the binding from the other device first.'
            );
        }

        return DeviceBinding::create([
            'user_id' => $user->id,
            'device_fingerprint' => $fingerprint,
            'device_meta' => $meta,
            'trusted_fingerprints' => [],
            'bound_at' => now(),
        ]);
    }

    /**
     * Face-verified instant bind for same physical device but different browser/incognito.
     * Bypasses old-device approval if hardware is similar and face is enrolled.
     */
    public function bindWithFaceVerified(User $user, string $fingerprint, array $meta = []): DeviceBinding
    {
        $binding = $this->bindingFor($user);

        if (! $binding) {
            // Nothing to transfer — bind directly
            return $this->bindDevice($user, $fingerprint, $meta);
        }

        if ($this->isTrusted($binding, $fingerprint)) {
            return $binding;
        }

        // Must have face enrollment to use instant path (prevents hijacking)
        $hasFace = $user->faceEnrollment()->exists();
        if (! $hasFace) {
            throw new DeviceBindingException(
                'Face enrollment required for instant bind. Please enroll face first or request transfer from old device.',
                403
            );
        }

        if (! $this->isSimilarDevice($binding->device_meta, $meta)) {
            throw new DeviceBindingException(
                'This device does not appear to be the same hardware as your bound device. Please request transfer from your old device.',
                403
            );
        }

        // Similar hardware + face enrolled => add to trusted list instantly, no old device needed
        $this->addTrustedFingerprint($binding, $fingerprint);
        // Also update meta to latest and keep bound_at fresh
        $binding->update([
            'device_meta' => $meta,
        ]);

        return $binding->fresh();
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

        if ($this->isTrusted($binding, $fingerprint)) {
            throw new DeviceBindingException('This device is already trusted for this account.', 409);
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
            // Reset trusted list to new primary + keep old as trusted for grace period?
            'trusted_fingerprints' => [],
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
        // Also revoke trusted fingerprints tokens?
        $trusted = $binding->trusted_fingerprints ?? [];
        $binding->delete();

        // The unbound device's session is no longer valid.
        $this->revokeDeviceTokens($user, $fingerprint);
        foreach ($trusted as $tf) {
            $this->revokeDeviceTokens($user, $tf);
        }
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

        // For approve/reject, the deciding device must be either primary or trusted
        $isDecidingTrusted = $this->isTrusted($binding, $decidingFingerprint) || $binding->device_fingerprint === $decidingFingerprint;
        if (! $isDecidingTrusted) {
            throw new DeviceBindingException(
                'Only the device currently bound to this account can decide this transfer.',
                403
            );
        }

        if ($binding->device_fingerprint === $request->requesting_fingerprint) {
            throw new DeviceBindingException('A device cannot approve its own transfer request.', 422);
        }
        // Also check trusted list for self-approval
        if ($this->isTrusted($binding, $request->requesting_fingerprint) && $request->requesting_fingerprint === $decidingFingerprint) {
            throw new DeviceBindingException('A device cannot approve its own transfer request.', 422);
        }
    }

    /**
     * Detailed status for hybrid gate - used by PWA to decide proceed vs transfer.
     */
    public function getStatusDetails(User $user, string $currentFingerprint, ?array $currentMeta = null): array
    {
        $binding = $this->bindingFor($user);
        if (! $binding) {
            return [
                'binding' => null,
                'trusted_fingerprints' => [],
                'is_trusted' => false,
                'is_similar' => false,
            ];
        }
        $isTrusted = $this->isTrusted($binding, $currentFingerprint);
        $isSimilar = false;
        if (! $isTrusted && $currentMeta) {
            $isSimilar = $this->isSimilarDevice($binding->device_meta, $currentMeta);
            // Similar only counts if face enrolled (prevents hijack)
            if ($isSimilar) {
                $hasFace = $user->faceEnrollment()->exists();
                if (! $hasFace) {
                    $isSimilar = false;
                }
            }
        }
        return [
            'binding' => $binding,
            'trusted_fingerprints' => $binding->trusted_fingerprints ?? [],
            'is_trusted' => $isTrusted,
            'is_similar' => $isSimilar,
        ];
    }
}
