<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;
use App\Services\AccessScopeService;

class ReceiptPolicy
{
    public function __construct(private AccessScopeService $accessScopeService) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Receipt $receipt): bool
    {
        $payment = $receipt->payment;

        if ($payment !== null && $payment->user_id === $user->id) {
            return true;
        }

        return $user->isSuperAdmin()
            || ($user->hasOfficerRole()
                && $payment !== null
                && $payment->organization_id !== null
                && $this->accessScopeService->isWithinScope($user, $payment->organization));
    }
}
