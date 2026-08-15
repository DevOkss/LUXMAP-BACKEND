<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single source of truth for a student's outstanding obligations.
 *
 * Obligations are computed dynamically (never pre-inserted into the payments
 * table) and are anchored to the academic term in which they were incurred:
 * membership is resolved from the student's per-term enrollment snapshot, so a
 * student who shifts institutes/programs keeps historical obligations.
 *
 * A fee is outstanding when it is posted for the student's term and org and no
 * paid/exempted transaction exists for it. A penalty is outstanding when the
 * student missed required QR attendances on a required event and that event has
 * not been settled.
 *
 * Pending cashless submissions do NOT settle an obligation (the officer's
 * approval does), so obligations with unresolved submissions still appear as
 * outstanding; the UI surfaces them as "Pending Verification".
 */
class ObligationService
{
    public function __construct(
        private EligibilityService $eligibility,
        private PenaltyService $penalties,
        private AcademicTermService $terms
    ) {}

    /**
     * Outstanding obligations for a student, normalized for the API/UI.
     *
     * @return array{fees: Collection, penalties: Collection, total: float, term: string|null}
     */
    public function forUser(
        User $user,
        ?Organization $scope = null,
        ?AcademicTerm $term = null
    ): array {
        $term = $term ?? $this->terms->current();
        $orgs = $scope
            ? collect([$scope])->filter()
            : $this->eligibility->userOrganizationsForTerm($user, $term);

        $fees = $this->feeObligations($user, $orgs, $term);
        $penalties = $this->penalties->studentOutstanding($user, $term, $scope);

        $fees = $fees->map(fn (array $item) => [
            'type' => Payment::TYPE_FEE,
            'obligation_key' => PaymentSubmission::buildLockKey(
                $user->id, $item['org_id'] ?? $item['organization_id'] ?? 0, (int) $term?->id, Payment::TYPE_FEE, $item['id']
            ),
            ...$item,
        ]);

        $normalizedPenalties = $penalties->map(function (array $item) use ($user, $term) {
            return [
                'type' => Payment::TYPE_PENALTY,
                'obligation_id' => $item['event_id'],
                'obligation_key' => PaymentSubmission::buildLockKey(
                    $user->id, $item['event']['organization']['id'], (int) $term->id, Payment::TYPE_PENALTY, null, $item['event_id']
                ),
                'event_id' => $item['event_id'],
                'event' => $item['event'],
                'absences' => $item['absences'],
                'unit_amount' => $item['unit_amount'],
                'missing_qr_configurations' => $item['missing_qr_configurations'],
                'amount' => (float) $item['amount'],
                'academic_term' => $term?->displayName(),
            ];
        })->values();

        $total = round($fees->sum('amount') + $normalizedPenalties->sum('amount'), 2);

        return [
            'fees' => $fees->values(),
            'penalties' => $normalizedPenalties,
            'total' => $total,
            'term' => $term?->displayName(),
        ];
    }

    /**
     * Whether the obligation already has a confirmed ledger transaction
     * (paid or exempted). Pending submissions are ignored.
     */
    public function isSettled(
        User $user,
        int $organizationId,
        ?AcademicTerm $term,
        string $feeType,
        ?int $feeId,
        ?int $eventId
    ): bool {
        return Payment::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('fee_type', $feeType)
            ->where('fee_id', $feeId)
            ->where('event_id', $eventId)
            ->settled()
            ->exists();
    }

    /**
     * The dynamically computed amount of a single obligation, or null when it
     * does not currently exist. Used to re-validate submissions at approval
     * time (full-payment rule).
     */
    public function amountOf(
        User $user,
        Organization $organization,
        ?AcademicTerm $term,
        string $feeType,
        ?int $feeId,
        ?int $eventId
    ): ?float {
        if ($feeType === Payment::TYPE_FEE) {
            if (!$feeId || !$term) {
                return null;
            }

            $fee = Fee::find($feeId);
            if (!$fee || $fee->organization_id !== $organization->id) {
                return null;
            }

            $eligible = $this->eligibility->studentIds($organization, $fee->required_years ?? [], $term)
                ->contains($user->id);

            return $eligible ? (float) $fee->amount : null;
        }

        if (!$eventId) {
            return null;
        }

        $rows = $this->penalties->studentOutstanding($user, $term, $organization);
        $match = $rows->firstWhere('event_id', $eventId);

        return $match ? (float) $match['amount'] : null;
    }

    /**
     * Verify that the given fee/event selections are real, outstanding
     * obligations of the student for the org, and sum their amounts.
     *
     * @return array{organization_id: int, items: array{type: string, id: int, amount: float}[], total: float}|null
     */
    public function verifySelected(
        User $student,
        int $organizationId,
        array $feeIds = [],
        array $eventIds = [],
        ?AcademicTerm $term = null
    ): ?array {
        $organization = Organization::find($organizationId);

        if (!$organization) {
            return null;
        }

        $outstanding = $this->forUser($student, $organization, $term);

        $fees = $outstanding['fees']
            ->whereIn('id', $feeIds ?: [])
            ->map(fn ($fee) => ['type' => Payment::TYPE_FEE, 'id' => $fee['id'], 'amount' => (float) $fee['amount']]);

        $penalties = $outstanding['penalties']
            ->whereIn('event_id', $eventIds ?: [])
            ->map(fn ($p) => ['type' => Payment::TYPE_PENALTY, 'id' => $p['event_id'], 'amount' => (float) $p['amount']]);

        $items = $fees->concat($penalties)->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'organization_id' => $organizationId,
            'items' => $items->all(),
            'total' => round($items->sum('amount'), 2),
        ];
    }

    private function feeObligations(User $user, Collection $orgs, ?AcademicTerm $term): Collection
    {
        if ($orgs->isEmpty() || !$term) {
            return new Collection();
        }

        $settledFeeIds = Payment::isFee()
            ->where('user_id', $user->id)
            ->whereNotNull('fee_id')
            ->settled()
            ->pluck('fee_id')
            ->map(fn ($id) => (int) $id);

        return Fee::with('organization')
            ->posted()
            ->whereIn('organization_id', $orgs->pluck('id'))
            ->where('academic_term_id', $term->id)
            ->get()
            ->reject(fn (Fee $fee) => $settledFeeIds->contains($fee->id))
            ->filter(fn (Fee $fee) => $this->eligibility->studentIds(
                $fee->organization,
                $fee->required_years ?? [],
                $term
            )->contains($user->id))
            ->map(fn (Fee $fee) => [
                'id' => $fee->id,
                'org_id' => $fee->organization_id,
                'name' => $fee->name,
                'description' => $fee->description,
                'organization' => $fee->organization ? [
                    'id' => $fee->organization->id,
                    'name' => $fee->organization->name,
                    'type' => $fee->organization->type,
                ] : null,
                'academic_term' => $term->displayName(),
                'amount' => (float) $fee->amount,
                'required_years' => $fee->required_years ?? ['all'],
                'due_date' => $fee->due_date?->format('Y-m-d'),
            ]);
    }
}