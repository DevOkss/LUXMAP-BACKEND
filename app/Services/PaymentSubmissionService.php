<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Repositories\PaymentSubmissionRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
     * Student cashless payment submissions and officer verification.
     *
     * Students cannot self-approve; a `payments` ledger entry is created only
     * after an organization officer verifies the submission against the
     * organization's actual receiving account. The superadmin is view-only.
     */
class PaymentSubmissionService
{
    public function __construct(
        private PaymentSubmissionRepository $repository,
        private ObligationService $obligations,
        private PaymentService $payments,
        private AcademicTermService $terms,
        private NotificationService $notifications
    ) {}

    /**
     * Submit a cashless payment attempt for one or more outstanding
     * obligations of a single organization. One submission group shares a
     * single reference number + receipt image.
     *
     * @return array{group_key: string, total: float, count: int, organization_id: int}
     */
    public function submit(User $student, array $data): array
    {
        $organization = Organization::find($data['organization_id']);
        $term = $this->terms->current();

        if (!$organization) {
            throw ValidationException::withMessages(['organization_id' => 'Organization not found.']);
        }

        if (!$term) {
            throw ValidationException::withMessages(['academic_term' => 'There is no active academic term to pay for.']);
        }

        $selected = $this->obligations->verifySelected(
            $student,
            $organization->id,
            $data['fee_ids'] ?? [],
            $data['event_ids'] ?? [],
            $term
        );

        if (!$selected) {
            throw ValidationException::withMessages(['items' => 'No outstanding obligations were selected.']);
        }

        $pendingKeys = [];
        foreach ($selected['items'] as $item) {
            $key = PaymentSubmission::buildLockKey(
                $student->id,
                $selected['organization_id'],
                $term->id,
                $item['type'],
                $item['type'] === Payment::TYPE_FEE ? $item['id'] : null,
                $item['type'] === Payment::TYPE_PENALTY ? $item['id'] : null,
            );
$pendingKeys[] = $key;

            if (PaymentSubmission::where('lock_key', $key)->exists()) {
                throw ValidationException::withMessages([
                    'items' => 'One of the selected obligations already has a payment submission pending verification.',
                ]);
            }
        }

        $receiptPath = null;
        if (isset($data['receipt_image']) && $data['receipt_image'] instanceof UploadedFile) {
            $receiptPath = $data['receipt_image']->store('payment-receipts', 'public');
        }

        $groupKey = 'psub_' . Str::uuid();

        DB::transaction(function () use ($selected, $student, $term, $data, $pendingKeys, $groupKey, $receiptPath) {
            foreach ($selected['items'] as $index => $item) {
                $this->repository->create([
                    'user_id' => $student->id,
                    'organization_id' => $selected['organization_id'],
                    'academic_term_id' => $term->id,
                    'fee_type' => $item['type'],
                    'fee_id' => $item['type'] === Payment::TYPE_FEE ? $item['id'] : null,
                    'event_id' => $item['type'] === Payment::TYPE_PENALTY ? $item['id'] : null,
                    'amount' => $item['amount'],
                    'payment_method' => PaymentSubmission::METHOD_CASHLESS,
                    'payment_channel' => $data['payment_channel'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'receipt_image' => $receiptPath,
                    'group_key' => $groupKey,
                    'status' => PaymentSubmission::STATUS_PENDING,
                    'lock_key' => $pendingKeys[$index],
                ]);
            }
        });

        $this->notifyReviewers($organization, $student);

        return [
            'group_key' => $groupKey,
            'organization_id' => $selected['organization_id'],
            'total' => $selected['total'],
            'count' => count($selected['items']),
        ];
    }

    /**
     * Approve a pending submission group, creating one confirmed `payments`
     * transaction (plus official receipt) per obligation item.
     *
     * @return Collection<int, Payment>
     */
    public function approve(User $officer, string $groupKey): Collection
    {
        return DB::transaction(function () use ($officer, $groupKey) {
            $rows = $this->repository->pendingByGroup($groupKey);

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['submission' => 'No pending submission found for this group.']);
            }

            $organization = $rows->first()->organization;
            $this->authorizeVerifier($officer, $organization);

            $student = $rows->first()->user;
            $term = $rows->first()->academicTerm;

            // Re-validate each obligation: still outstanding + full payment.
            foreach ($rows as $row) {
                $expected = $this->obligations->amountOf(
                    $student,
                    $organization,
                    $term,
                    $row->fee_type,
                    $row->fee_id,
                    $row->event_id
                );

                if ($expected === null) {
                    throw ValidationException::withMessages([
                        'submission' => 'An obligation in this submission is no longer outstanding. Please reject it.',
                    ]);
                }

                if (abs((float) $row->amount - $expected) > 0.009) {
                    throw ValidationException::withMessages([
                        'submission' => 'The submitted amount does not match the current obligation amount (full payment required). Verify and reject if necessary.',
                    ]);
                }
            }

            $payments = new Collection();

            foreach ($rows as $row) {
                $payments->push($this->payments->settleFromSubmission($officer, $student, $row));

                $row->fill([
                    'status' => PaymentSubmission::STATUS_APPROVED,
                    'verified_by' => $officer->id,
                    'verified_at' => now(),
                    'lock_key' => null,
                ])->save();
            }

            $total = number_format((float) $rows->sum('amount'), 2);
            $this->notifications->notifyUser(
                $student,
                'Payment approved',
                "Your payment of ₱{$total} (ref: {$rows->first()->reference_number}) has been verified and recorded.",
                ['type' => 'payment_approved']
            );

            return $payments;
        });
    }

    /**
     * Reject a pending submission group with a required reason. Obligations
     * remain outstanding and may be resubmitted.
     */
    public function reject(User $officer, string $groupKey, string $reason): void
    {
        DB::transaction(function () use ($officer, $groupKey, $reason) {
            $rows = $this->repository->pendingByGroup($groupKey);

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['submission' => 'No pending submission found for this group.']);
            }

            $organization = $rows->first()->organization;
            $this->authorizeVerifier($officer, $organization);

            $now = now();
            foreach ($rows as $row) {
                $row->fill([
                    'status' => PaymentSubmission::STATUS_REJECTED,
                    'verified_by' => $officer->id,
                    'verified_at' => $now,
                    'rejection_reason' => $reason,
                    'lock_key' => null,
                ])->save();
            }

            $this->notifications->notifyUser(
                $rows->first()->user,
                'Payment submission rejected',
                "Your payment submission was rejected. Reason: {$reason}",
                ['type' => 'payment_rejected']
            );
        });
    }

    /**
     * lock_keys of obligations that currently have an unresolved submission
     * (pending or approved) — used by the UI to show "Pending Verification".
     */
    public function unresolvedKeys(User $user): array
    {
        return PaymentSubmission::query()
            ->forUser($user->id)
            ->unresolved()
            ->pluck('lock_key')
            ->all();
    }

    public function pendingGroups(array $organizationIds, array $filters = []): Collection
    {
        return $this->repository->pendingGroups($organizationIds, $filters);
    }

    public function groupRows(string $groupKey): Collection
    {
        return $this->repository->groupRows($groupKey);
    }

    public function userGroups(User $user): Collection
    {
        return $this->repository->userGroups($user->id);
    }

    public function show(int $id): ?PaymentSubmission
    {
        return $this->repository->find($id);
    }

    private function authorizeVerifier(User $officer, Organization $organization): void
    {
        $role = $officer->roleInOrganization($organization);

        $allowed = $role !== null && in_array($role, UserRole::staffRoles(), true);

        if (!$allowed) {
            abort(403, 'You are not authorized to verify payments.');
        }
    }

    private function notifyReviewers(Organization $organization, User $student): void
    {
        $reviewers = $organization->users()
            ->wherePivotIn('role', UserRole::officerRoles())
            ->get();

        foreach ($reviewers as $reviewer) {
            $this->notifications->notifyUser(
                $reviewer,
                'New payment submission',
                "{$student->name} ({$student->student_number}) submitted a payment for {$organization->name}.",
                ['type' => 'payment_submission', 'organization_id' => $organization->id]
            );
        }
    }
}