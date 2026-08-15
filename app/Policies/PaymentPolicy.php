<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Services\AccessScopeService;

class PaymentPolicy
{
    public function __construct(private AccessScopeService $accessScopeService) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($payment->user_id === $user->id) {
            return true;
        }

        return $this->manageable($user, $payment);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function complete(User $user, Payment $payment): bool
    {
        return $this->manageable($user, $payment);
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $this->manageable($user, $payment);
    }

    private function manageable(User $user, Payment $payment): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasStaffRole()
                && $payment->organization_id !== null
                && $this->accessScopeService->isWithinScope($user, $payment->organization));
    }
}
