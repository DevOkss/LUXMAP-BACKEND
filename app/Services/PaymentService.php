<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\Receipt;
use App\Models\User;
use App\Repositories\PaymentRepository;
use App\Repositories\ReceiptRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private ReceiptRepository $receiptRepository,
        private AcademicTermService $terms,
        private NotificationService $notifications
    ) {}

    public function list(array $filters = []): EloquentCollection
    {
        return $this->paymentRepository->all($filters);
    }

    public function paginatedList(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->paymentRepository->paginate($filters, $perPage);
    }

    /**
     * Sum of collected transaction amounts matching the filters. Exempted
     * (waived) obligations are settled but never collected, so they are
     * excluded from the transaction total.
     */
    public function totalAmount(array $filters = []): float
    {
        return $this->paymentRepository->sum($filters + ['exclude_exempted' => true]);
    }

    public function show(int $id): ?Payment
    {
        return $this->paymentRepository->find($id);
    }

    public function showByUuid(string $uuid): ?Payment
    {
        return $this->paymentRepository->findByUuid($uuid);
    }

    public function paginateBatches(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->paymentRepository->paginateBatches($filters, $perPage);
    }

    public function forBatches(array $batchIds, array $filters = []): EloquentCollection
    {
        return $this->paymentRepository->forBatches($batchIds, $filters);
    }

    public function delete(int $id): bool
    {
        $payment = $this->paymentRepository->find($id);
        if (! $payment) {
            return false;
        }

        return $this->paymentRepository->delete($payment);
    }

    public function userPayments(int $userId): EloquentCollection
    {
        return $this->paymentRepository->findByUser($userId);
    }

    /**
     * Record an on-site cash payment for an already-verified selection of
     * outstanding obligations (see ObligationService::verifySelected).
     * Creates one paid transaction per obligation but exactly ONE receipt
     * for the whole batch (transaction). The student receives a confirmation
     * notification with total, selected fees and the receipt reference.
     *
     * @param  array{organization_id: int, items: array, total: float}  $selected
     * @return Collection<int, Payment>
     */
    public function recordCash(User $officer, User $student, array $selected, ?string $notes = null): Collection
    {
        $term = $this->terms->current();

        if (! $term) {
            throw new \RuntimeException('There is no active academic term.');
        }

        $batchId = (string) Str::uuid();

        return DB::transaction(function () use ($officer, $student, $selected, $notes, $term, $batchId) {
            $payments = new Collection;

            foreach ($selected['items'] as $item) {
                $payments->push($this->createPaymentRow(
                    student: $student,
                    organizationId: $selected['organization_id'],
                    term: $term,
                    feeType: $item['type'],
                    obligationId: $item['id'],
                    amount: $item['amount'],
                    method: Payment::METHOD_CASH,
                    status: Payment::STATUS_PAID,
                    paidAt: now(),
                    notes: $notes,
                    processedBy: $officer->id,
                    batchId: $batchId,
                ));
            }

            $receipt = $this->generateReceiptForBatch($payments, $officer->id);

            // Notify student — single notification per transaction, with breakdown.
            $orgName = $payments->first()?->organization?->name;
            if (! $orgName) {
                $org = \App\Models\Organization::find($selected['organization_id']);
                $orgName = $org?->name;
            }
            try {
                $this->notifications->notifyPaymentRecorded($student, $payments, $receipt, $orgName);
            } catch (\Throwable $e) {
                // Never fail the transaction on push error (see WebPushService hardening).
                report($e);
            }

            return $payments;
        });
    }

    /**
     * Exempt/waive an already-verified set of outstanding obligations.
     * Creates one transaction per item flagged as exempted but exactly ONE
     * receipt for the batch.
     */
    public function exemptObligations(
        User $officer,
        User $student,
        array $selected,
        string $reason
    ): Collection {
        $term = $this->terms->current();

        if (! $term) {
            throw new \RuntimeException('There is no active academic term.');
        }

        $batchId = (string) Str::uuid();

        return DB::transaction(function () use ($officer, $student, $selected, $reason, $term, $batchId) {
            $payments = new Collection;

            foreach ($selected['items'] as $item) {
                $payments->push($this->createPaymentRow(
                    student: $student,
                    organizationId: $selected['organization_id'],
                    term: $term,
                    feeType: $item['type'],
                    obligationId: $item['id'],
                    amount: $item['amount'],
                    method: Payment::METHOD_EXEMPTION,
                    status: Payment::STATUS_EXEMPTED,
                    paidAt: null,
                    notes: $reason,
                    exemptedBy: $officer->id,
                    batchId: $batchId,
                ));
            }

            $this->generateReceiptForBatch($payments, $officer->id);

            return $payments;
        });
    }

    /**
     * Create the confirmed ledger transaction once an officer approves a
     * verified cashless submission. For a single submission row (legacy).
     * Generates one receipt for this single-row batch.
     */
    public function settleFromSubmission(
        User $officer,
        User $student,
        PaymentSubmission $submission,
        ?string $batchId = null,
        bool $withReceipt = true
    ): Payment {
        $batchId = $batchId ?: (string) Str::uuid();

        $payment = $this->paymentRepository->create([
            'uuid' => (string) Str::uuid(),
            'batch_id' => $batchId,
            'user_id' => $student->id,
            'organization_id' => $submission->organization_id,
            'academic_term_id' => $submission->academic_term_id,
            'fee_type' => $submission->fee_type,
            'fee_id' => $submission->fee_type === Payment::TYPE_FEE ? $submission->fee_id : null,
            'event_id' => $submission->fee_type === Payment::TYPE_PENALTY ? $submission->event_id : null,
            'amount' => (float) $submission->amount,
            'payment_method' => Payment::METHOD_CASHLESS,
            'reference_number' => $submission->reference_number,
            'payment_submission_id' => $submission->id,
            'status' => Payment::STATUS_PAID,
            'isExempted' => false,
            'processed_by' => $officer->id,
            'paid_at' => now(),
        ]);

        if ($withReceipt) {
            $this->generateReceiptForBatch(new Collection([$payment]), $officer->id);
        }

        return $payment;
    }

    /**
     * Batch settlement for a whole submission group (multiple obligations
     * submitted together). Creates N payments sharing one batch_id + ONE receipt.
     *
     * @param  Collection<int, PaymentSubmission>  $submissions
     * @return Collection<int, Payment>
     */
    public function settleBatchFromSubmissions(User $officer, User $student, Collection $submissions): Collection
    {
        if ($submissions->isEmpty()) {
            return new Collection;
        }

        $batchId = (string) Str::uuid();
        $payments = new Collection;

        foreach ($submissions as $submission) {
            $payments->push($this->settleFromSubmission($officer, $student, $submission, $batchId, false));
        }

        $this->generateReceiptForBatch($payments, $officer->id);

        return $payments;
    }

    public function generateReceipt(Payment $payment, ?int $issuedBy = null): Receipt
    {
        return $this->receiptRepository->create([
            'payment_id' => $payment->id,
            'batch_id' => $payment->batch_id,
            'receipt_number' => $this->receiptRepository->generateReceiptNumber(),
            'issued_at' => $payment->paid_at ?? now(),
            'issued_by' => $issuedBy,
        ]);
    }

    /**
     * Create a single settled payment row without generating a receipt.
     */
    private function createPaymentRow(
        User $student,
        int $organizationId,
        AcademicTerm $term,
        string $feeType,
        int $obligationId,
        float $amount,
        string $method,
        string $status,
        ?\DateTimeInterface $paidAt,
        ?string $notes = null,
        ?int $processedBy = null,
        ?int $exemptedBy = null,
        ?string $batchId = null,
    ): Payment {
        return $this->paymentRepository->create([
            'uuid' => (string) Str::uuid(),
            'batch_id' => $batchId,
            'user_id' => $student->id,
            'organization_id' => $organizationId,
            'academic_term_id' => $term->id,
            'fee_type' => $feeType,
            'fee_id' => $feeType === Payment::TYPE_FEE ? $obligationId : null,
            'event_id' => $feeType === Payment::TYPE_PENALTY ? $obligationId : null,
            'amount' => $amount,
            'payment_method' => $method,
            'status' => $status,
            'isExempted' => $status === Payment::STATUS_EXEMPTED,
            'exempted_by' => $exemptedBy,
            'exempted_at' => $status === Payment::STATUS_EXEMPTED ? now() : null,
            'processed_by' => $processedBy,
            'paid_at' => $paidAt,
            'notes' => $notes,
        ]);
    }

    /**
     * Legacy helper kept for single-payment receipt creation (not used by
     * batch flows any more). Retained for backwards compatibility.
     */
    private function createSettledTransaction(
        User $student,
        int $organizationId,
        AcademicTerm $term,
        string $feeType,
        int $obligationId,
        float $amount,
        string $method,
        string $status,
        ?\DateTimeInterface $paidAt,
        ?string $notes = null,
        ?int $processedBy = null,
        ?int $exemptedBy = null,
        ?string $reason = null,
        ?string $batchId = null,
    ): Payment {
        $payment = $this->createPaymentRow(
            $student, $organizationId, $term, $feeType, $obligationId, $amount, $method, $status, $paidAt, $notes, $processedBy, $exemptedBy, $batchId
        );

        $this->generateReceiptForBatch(new Collection([$payment]), $processedBy ?? $exemptedBy);

        return $payment;
    }

    private function generateReceiptFor(Payment $payment, ?int $processedBy = null, ?int $exemptedBy = null): void
    {
        if ($payment->receipt) {
            return;
        }

        $this->receiptRepository->create([
            'payment_id' => $payment->id,
            'batch_id' => $payment->batch_id,
            'receipt_number' => $this->receiptRepository->generateReceiptNumber(),
            'issued_at' => now(),
            'issued_by' => $processedBy ?? $exemptedBy,
        ]);
    }

    /**
     * Generate exactly ONE receipt for the whole batch. The receipt is linked
     * to the first payment in the batch but carries the batch_id so the full
     * breakdown can be reconstructed.
     */
    public function generateReceiptForBatch(Collection $payments, ?int $issuedBy = null): Receipt
    {
        if ($payments->isEmpty()) {
            throw new \InvalidArgumentException('Cannot generate receipt for empty batch.');
        }

        $first = $payments->first();
        $batchId = $first->batch_id;

        // Guard: if a receipt already exists for this batch, reuse it.
        $existing = $this->receiptRepository->findByBatchId($batchId);
        if ($existing) {
            return $existing;
        }

        return $this->receiptRepository->create([
            'payment_id' => $first->id,
            'batch_id' => $batchId,
            'receipt_number' => $this->receiptRepository->generateReceiptNumber(),
            'issued_at' => $first->paid_at ?? now(),
            'issued_by' => $issuedBy,
        ]);
    }
}
