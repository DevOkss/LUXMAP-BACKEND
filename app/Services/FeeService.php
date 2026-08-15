<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Fee;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\FeeRepository;
use Illuminate\Database\Eloquent\Collection;

class FeeService
{
    public function __construct(
        private FeeRepository $repository,
        private EligibilityService $eligibility,
        private AcademicTermService $terms
    ) {}

    public function list(array $filters = []): Collection
    {
        return $this->repository->all($filters);
    }

    public function show(int $id): ?Fee
    {
        return $this->repository->find($id);
    }

    public function create(array $data): Fee
    {
        $data['status'] = $data['status'] ?? 'draft';
        $data['required_years'] = $data['required_years'] ?? ['all'];
        $data['academic_term_id'] = $data['academic_term_id'] ?? $this->terms->current()?->id;
        $data['term'] = $data['term'] ?? $this->termLabel($data['academic_term_id']);

        return $this->repository->create($data);
    }

    public function update(int $id, array $data): ?Fee
    {
        $fee = $this->repository->find($id);
        if (!$fee) {
            return null;
        }

        if (array_key_exists('academic_term_id', $data)) {
            $data['academic_term_id'] = $data['academic_term_id'] ?? $fee->academic_term_id;
            $data['term'] = $this->termLabel($data['academic_term_id']);
        }

        $this->repository->update($fee, $data);
        return $fee->fresh();
    }

    private function termLabel(?int $academicTermId): ?string
    {
        return $academicTermId ? AcademicTerm::find($academicTermId)?->displayName() : null;
    }

    public function delete(int $id): bool
    {
        $fee = $this->repository->find($id);
        if (!$fee) {
            return false;
        }

        return $this->repository->delete($fee);
    }

    public function publish(Fee $fee): Fee
    {
        $this->repository->update($fee, ['status' => 'posted']);
        return $fee->fresh();
    }

    public function unpublish(Fee $fee): Fee
    {
        $this->repository->update($fee, ['status' => 'draft']);
        return $fee->fresh();
    }

    /**
     * Number of currently-eligible students for a fee (org scope + required
     * years within the fee's academic term).
     */
    public function eligibleCount(Fee $fee): int
    {
        $org = $fee->organization;
        if (!$org) {
            return 0;
        }

        return $this->eligibility->studentIds($org, $fee->required_years ?? [], $fee->academicTerm)->count();
    }

    /**
     * The posted fees a student is obligated to pay (computed dynamically),
     * each annotated with the student's derived status:
     *   - 'paid'       when a completed payment exists
     *   - 'exempted'   when a payment has been exempted by a head
     *   - 'pending'    otherwise (still owed)
     */
    public function studentObligations(User $user): Collection
    {
        $orgs = $this->eligibility->userOrganizations($user);

        if ($orgs->isEmpty()) {
            return new Collection();
        }

        $term = $this->terms->current();

        $fees = Fee::with('organization')
            ->posted()
            ->whereIn('organization_id', $orgs->pluck('id'))
            ->when($term, fn ($q) => $q->where('academic_term_id', $term->id))
            ->get()
            ->filter(function (Fee $fee) use ($user) {
                $org = $fee->organization;
                if (!$org) {
                    return false;
                }

                return $this->eligibility->studentIds($org, $fee->required_years ?? [], $fee->academicTerm)
                    ->contains($user->id);
            });

        $payments = Payment::with('fee')
            ->isFee()
            ->where('user_id', $user->id)
            ->whereIn('status', [Payment::STATUS_PAID])
            ->get()
            ->groupBy('fee_id');

        return $fees->map(function (Fee $fee) use ($user, $payments) {
            $paid = $payments->get($fee->id);
            $status = $paid && $paid->isNotEmpty() ? 'paid' : 'due';

            if ($status !== 'paid') {
                $exempted = Payment::isFee()
                    ->where('user_id', $user->id)
                    ->where('fee_id', $fee->id)
                    ->where('isExempted', true)
                    ->exists();
                if ($exempted) {
                    $status = 'exempted';
                }
            }

            $fee->setAttribute('obligation_status', $status);
            return $fee;
        });
    }
}