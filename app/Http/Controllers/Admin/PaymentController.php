<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExemptObligationsRequest;
use App\Http\Requests\RecordCashRequest;
use App\Models\AcademicTerm;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\AcademicTermService;
use App\Services\AccessScopeService;
use App\Services\EligibilityService;
use App\Services\ObligationService;
use App\Services\PaymentAccountService;
use App\Services\PaymentExportService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private PaymentExportService $paymentExports,
        private ObligationService $obligations,
        private PaymentAccountService $accounts,
        private AccessScopeService $accessScope,
        private AcademicTermService $terms,
        private EligibilityService $eligibility
    ) {}

    /**
     * The Payments module. Renders the Shared Index page with the active tab
     * dataset: transactions (ledger), pending verification (submissions) and
     * outstanding (dynamically computed balances).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab', 'transactions');
        $orgIds = $this->accessScope->scopeOrganizationIds($user);

        $term = $this->resolveTerm((int) $request->query('academic_term_id', 0))
            ?? $this->terms->current();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 30);
        if ($perPage < 1) {
            $perPage = 30;
        }

        // Transactions (ledger), grouped into batches — one row per cash/exempt
        // session. A student's membership + penalty exemption is a single row.
        $filters = $request->only(['status', 'payment_method', 'fee_type', 'user_id', 'search', 'date_from', 'date_to']);
        $filters['organization_ids'] = $orgIds;
        if ($term) {
            $filters['academic_term_id'] = $term->id;
        }

        $transactionsTotal = $this->paymentService->totalAmount($filters);
        $batchesPage = $this->paymentService->paginateBatches($filters, $perPage);
        $batchPayments = $this->paymentService->forBatches(
            collect($batchesPage->items())->pluck('batch_id')->all(),
            $filters
        );
        $transactions = $this->groupTransactionBatches($batchPayments);

        // Pending verifications (grouped cashless submissions)
        $pending = $this->pendingSnapshot($request, $orgIds, $term, $page, $perPage);

        // Outstanding students: dynamically computed balances across every
        // eligible student in scope for the selected term.
        $outstanding = $this->outstandingSnapshot($request, $orgIds, $term, $page, $perPage);

        return Inertia::render('admin/payments/Index', [
            'tab' => $tab,
            'transactions' => $transactions,
            'transactions_pagination' => [
                'current_page' => $batchesPage->currentPage(),
                'last_page' => $batchesPage->lastPage(),
                'total' => $batchesPage->total(),
                'per_page' => $batchesPage->perPage(),
            ],
            'transactions_total' => round($transactionsTotal, 2),
            'pending' => $pending['groups'],
            'pending_pagination' => $pending['pagination'],
            'pending_total' => $pending['total'],
            'pending_search' => $pending['search'],
            'outstanding' => $outstanding,
            'outstanding_total' => $outstanding['total'],
            'filters' => $request->only(['status', 'payment_method', 'fee_type', 'search', 'academic_term_id', 'date_from', 'date_to']),
            'academic_terms' => $this->academicTermsForPicker(),
            'export_fees' => $this->exportableFees($orgIds, $term),
            'export_penalty_events' => $this->exportablePenaltyEvents($orgIds, $term),
            'selected_term' => $term?->id ?? null,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Download transactions for the given filters as an Excel workbook.
     * Scoped to the user's organizations and the selected academic term.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'organization_ids' => $this->accessScope->scopeOrganizationIds($request->user()),
            'academic_term_id' => (int) $request->query('academic_term_id', 0) ?: null,            'include_fees' => filter_var($request->query('include_fees', true), FILTER_VALIDATE_BOOLEAN),
            'include_penalties' => filter_var($request->query('include_penalties', true), FILTER_VALIDATE_BOOLEAN),
            'fee_ids' => $this->parseIdList($request->query('fee_ids')),
            'event_ids' => $this->parseIdList($request->query('event_ids')),
        ];

        return $this->paymentExports->stream($filters);
    }

    /**
     * Parse a comma-separated or repeated id query value into an int array.
     */
    private function parseIdList(mixed $value): array
    {
        $items = is_array($value) ? $value : (preg_split('/,/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return array_values(array_filter(array_map('intval', $items)));
    }

    /**
     * Posted fees available to the user's scope for the selected term.
     */
    private function exportableFees(array $orgIds, ?AcademicTerm $term): array
    {
        return \App\Models\Fee::query()
            ->whereIn('organization_id', $orgIds)
            ->when($term, fn ($q) => $q->where('academic_term_id', $term->id))
            ->posted()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($fee) => ['id' => $fee->id, 'label' => $fee->name])
            ->values()
            ->all();
    }

    /**
     * Published/completed events in the user's scope for the selected term —
     * the events that can carry penalty transactions.
     */
    private function exportablePenaltyEvents(array $orgIds, ?AcademicTerm $term): array
    {
        return \App\Models\Event::query()
            ->whereIn('organization_id', $orgIds)
            ->whereIn('status', ['published', 'completed'])
            ->when($term, fn ($q) => $q->where('academic_term_id', $term->id))
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn ($event) => ['id' => $event->id, 'label' => $event->title])
            ->values()
            ->all();
    }

    /**
     * A single student's outstanding obligations within the officer's scope,
     * for recording a cash payment or granting an exemption.
     */
    public function studentDetail(Request $request, int $userId)
    {
        $user = $request->user();
        $orgIds = $this->accessScope->scopeOrganizationIds($user);

        $student = User::find($userId);
        if (! $student) {
            return redirect()->route('admin.payments.index')->with('error', 'Student not found.');
        }

        $term = $this->terms->current();
        $data = $this->obligations->forUser($student, null, $term);

        $fees = $data['fees']->filter(fn ($fee) => in_array($fee['organization']['id'] ?? null, $orgIds, true))->values();
        $penalties = $data['penalties']->filter(fn ($pen) => in_array($pen['event']['organization']['id'] ?? null, $orgIds, true))->values();

        $organizations = $this->scopedOrganizations($user)->map(fn (Organization $org) => [
            'id' => $org->id,
            'name' => $org->name,
            'type' => $org->type,
            'payment_account' => $this->accountFor($this->accounts->forOrganization($org->id)),
        ])->values();

        return Inertia::render('admin/payments/StudentDetail', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'email' => $student->email,
                'course_program' => $student->program?->name ?? null,
                'year_level' => $student->year_level,
            ],
            'fees' => $fees,
            'penalties' => $penalties,
            'organizations' => $organizations,
            'term' => $term?->displayName(),
            'can_process' => $this->canProcess($user),
        ]);
    }

    public function recordCash(RecordCashRequest $request)
    {
        $user = $request->user();
        $org = Organization::findOrFail($request->input('organization_id'));
        $this->authorizeProcessor($user, $org);

        $student = User::findOrFail($request->integer('user_id'));
        $term = $this->terms->current();

        $selected = $this->obligations->verifySelected(
            $student,
            $org->id,
            $request->input('fee_ids') ?? [],
            $request->input('event_ids') ?? [],
            $term
        );

        if (! $selected) {
            throw ValidationException::withMessages(['items' => 'No outstanding obligations selected for cash payment.']);
        }

        try {
            $payments = $this->paymentService->recordCash($user, $student, $selected, $request->input('notes'));
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payments.students.detail', $student->id)
            ->with('success', count($payments).' payment(s) recorded successfully.');
    }

    public function exempt(ExemptObligationsRequest $request)
    {
        $user = $request->user();
        $org = Organization::findOrFail($request->input('organization_id'));
        $this->authorizeProcessor($user, $org);

        $student = User::findOrFail($request->integer('user_id'));
        $term = $this->terms->current();

        $selected = $this->obligations->verifySelected(
            $student,
            $org->id,
            $request->input('fee_ids') ?? [],
            $request->input('event_ids') ?? [],
            $term
        );

        if (! $selected) {
            throw ValidationException::withMessages(['items' => 'No outstanding obligations selected for exemption.']);
        }

        try {
            $payments = $this->paymentService->exemptObligations($user, $student, $selected, $request->input('reason'));
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payments.students.detail', $student->id)
            ->with('success', count($payments).' obligation(s) exempted successfully.');
    }

    public function show(Request $request, string $uuid)
    {
        $payment = $this->paymentService->showByUuid($uuid);
        if (! $payment) {
            return redirect()->route('admin.payments.index')->with('error', 'Payment not found.');
        }

        if ($payment->organization && ! $this->accessScope->isWithinScope($request->user(), $payment->organization)) {
            abort(403, 'This payment is outside your scope.');
        }

        $batchPayments = $payment->batch_id
            ? $this->paymentService->forBatches([$payment->batch_id], [])->values()
            : collect([$payment]);

        $batch = $this->batchDetailRow($payment, $batchPayments);

        // All of the student's payments for the same term, grouped into batches.
        $orgIds = $this->accessScope->scopeOrganizationIds($request->user());
        $termPayments = Payment::with([
            'user', 'organization', 'academicTerm', 'fee', 'receipt', 'event', 'exemptedBy', 'processedBy', 'event.organization',
        ])
            ->where('user_id', $payment->user_id)
            ->where('academic_term_id', $payment->academic_term_id)
            ->when($orgIds, fn ($q) => $q->whereIn('organization_id', $orgIds))
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('admin/payments/Show', [
            'batch' => $batch,
            'student' => $payment->user ? [
                'id' => $payment->user->id,
                'name' => $payment->user->name,
                'student_number' => $payment->user->student_number,
            ] : null,
            'organization' => $payment->organization ? [
                'id' => $payment->organization->id,
                'name' => $payment->organization->name,
            ] : null,
            'term' => $payment->academicTerm?->displayName(),
            'history' => $this->groupTransactionBatches($termPayments),
            'can_process' => $this->canProcess($request->user()),
        ]);
    }

    private function pendingSnapshot(Request $request, array $orgIds, ?AcademicTerm $term, int $page, int $perPage): array
    {
        $search = trim((string) $request->query('pending_search', ''));

        $query = PaymentSubmission::with([
            'user', 'organization', 'academicTerm', 'fee', 'event', 'verifiedBy',
        ])
            ->pending()
            ->whereIn('organization_id', $orgIds)
            ->when($term, fn ($q) => $q->where('academic_term_id', $term->id))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('student_number', 'like', "%{$search}%")))
            ->orderBy('created_at', 'desc');

        $rows = (clone $query)->get();

        $groups = $rows->groupBy('group_key')
            ->map(fn ($r) => $this->submissionGroupRow($r))
            ->values();

        $count = $groups->count();

        return [
            'groups' => $groups->slice(($page - 1) * $perPage, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($count / $perPage)),
                'total' => $count,
                'per_page' => $perPage,
            ],
            'total' => round($rows->sum('amount'), 2),
            'search' => $search,
        ];
    }

    private function outstandingSnapshot(Request $request, array $orgIds, ?AcademicTerm $term, int $page, int $perPage): array
    {
        $search = trim((string) $request->query('search', ''));

        $orgs = Organization::whereIn('id', $orgIds)->get();

        $eligibleIds = collect();
        foreach ($orgs as $org) {
            $eligibleIds = $eligibleIds->concat($this->eligibility->studentIds($org, [], $term));
        }
        $eligibleIds = $eligibleIds->unique()->values();

        $users = User::whereIn('id', $eligibleIds->all())
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")
                ->orWhere('student_number', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get();

        $rows = $users->map(function (User $student) use ($term, $orgIds) {
            $data = $this->obligations->forUser($student, null, $term);

            $total = collect($data['fees'])
                ->filter(fn ($fee) => in_array($fee['organization']['id'] ?? null, $orgIds, true))
                ->sum('amount')
                + $data['penalties']->filter(fn ($p) => in_array($p['event']['organization']['id'] ?? null, $orgIds, true))->sum('amount');

            return [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'year_level' => $student->year_level,
                'organizations' => $orgIds,
                'total_balance' => round($total, 2),
                'has_obligations' => $total > 0,
            ];
        })->values();

        // Students with outstanding balances first; cleared students sink to bottom.
        $rows = $rows->sortByDesc('total_balance')->values();

        $count = $rows->count();

        return [
            'students' => $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            'total' => round($rows->sum('total_balance'), 2),
            'searched' => $search !== '' || $count > 0,
            'search' => $search,
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($count / $perPage)),
                'total' => $count,
                'per_page' => $perPage,
            ],
        ];
    }

    private function scopedOrganizations(User $user): Collection
    {
        return Organization::whereIn('id', $this->accessScope->scopeOrganizationIds($user))->get();
    }

    private function resolveTerm(int $academicTermId): ?AcademicTerm
    {
        if ($academicTermId <= 0) {
            return null;
        }

        return AcademicTerm::find($academicTermId);
    }

    private function academicTermsForPicker(): array
    {
        return AcademicTerm::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get(['id', 'academic_year', 'semester', 'is_active'])
            ->map(fn (AcademicTerm $term) => [
                'id' => $term->id,
                'name' => $term->displayName(),
                'is_active' => $term->is_active,
            ])
            ->values()
            ->all();
    }

    private function canProcess(User $user): bool
    {
        return $user->hasRole(UserRole::staffRoles());
    }

    private function authorizeProcessor(User $user, Organization $org): void
    {
        if ($user->isSuperAdmin()) {
            abort(403, 'Super admin is view-only for payment actions.');
        }

        $role = $user->roleInOrganization($org);
        $isStaff = $role !== null && in_array($role, UserRole::staffRoles(), true);

        if (! $isStaff) {
            abort(403, 'You are not authorized to process payments in this organization.');
        }

        if (! $this->accessScope->isWithinScope($user, $org)) {
            abort(403, 'This organization is outside your scope.');
        }
    }

    private function accountFor(?PaymentAccount $account): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'id' => $account->id,
            'account_name' => $account->account_name,
            'account_provider' => $account->account_provider,
            'account_number' => $account->account_number,
            'qr_code_image_url' => $account->qr_code_image ? Storage::url($account->qr_code_image) : null,
            'is_active' => (bool) $account->is_active,
        ];
    }

    private function submissionGroupRow(Collection $rows): array
    {
        $first = $rows->first();

        return [
            'group_key' => $first->group_key,
            'status' => $first->status,
            'reference_number' => $first->reference_number,
            'payment_channel' => $first->payment_channel,
            'receipt_image_url' => $first->receipt_image ? Storage::url($first->receipt_image) : null,
            'submitted_at' => $first->created_at,
            'academic_term' => $first->academicTerm?->displayName(),
            'organization' => $first->organization ? ['id' => $first->organization->id, 'name' => $first->organization->name] : null,
            'student' => $first->user ? ['id' => $first->user->id, 'name' => $first->user->name, 'student_number' => $first->user->student_number] : null,
            'total' => round($rows->sum('amount'), 2),
            'items' => $rows->map(fn ($row) => [
                'fee_type' => $row->fee_type,
                'amount' => (float) $row->amount,
                'fee' => $row->fee ? ['id' => $row->fee->id, 'name' => $row->fee->name] : null,
                'event' => $row->event ? ['id' => $row->event->id, 'title' => $row->event->title] : null,
            ])->values(),
        ];
    }

    private function paymentRow(Payment $p): array
    {
        return [
            'id' => $p->id,
            'uuid' => $p->uuid,
            'batch_id' => $p->batch_id,
            'fee_type' => $p->fee_type,
            'amount' => (float) $p->amount,
            'payment_method' => $p->payment_method,
            'reference_number' => $p->reference_number,
            'status' => $p->status,
            'isExempted' => (bool) $p->isExempted,
            'paid_at' => $p->paid_at,
            'exempted_at' => $p->exempted_at,
            'academic_term' => $p->academicTerm?->displayName(),
            'user' => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name, 'student_number' => $p->user->student_number] : null,
            'organization' => $p->organization ? ['id' => $p->organization->id, 'name' => $p->organization->name] : null,
            'fee' => $p->fee ? ['id' => $p->fee->id, 'name' => $p->fee->name] : null,
            'event' => $p->event ? ['id' => $p->event->id, 'title' => $p->event->title] : null,
            'exemptedBy' => $p->exemptedBy ? ['id' => $p->exemptedBy->id, 'name' => $p->exemptedBy->name] : null,
            'processedBy' => $p->processedBy ? ['id' => $p->processedBy->id, 'name' => $p->processedBy->name] : null,
            'receipt' => $p->receipt ? ['id' => $p->receipt->id, 'receipt_number' => $p->receipt->receipt_number, 'issued_at' => $p->receipt->issued_at] : null,
        ];
    }

    /**
     * Group payments into transaction batches (payments sharing a batch_id).
     * Payments without a batch are treated as their own single-item batch.
     */
    private function groupTransactionBatches(Collection $payments): array
    {
        $groups = $payments->groupBy(fn (Payment $p) => $p->batch_id ?: 'p'.$p->id)
            ->sortByDesc(fn ($rows) => $rows->max('created_at'));

        return $groups->map(function ($rows) {
            $rows = $rows->values();
            $first = $rows->first();
            $last = $rows->last();
            $anyExempted = $rows->contains(fn (Payment $p) => (bool) $p->isExempted);
            $exemptedRow = $rows->first(fn (Payment $p) => (bool) $p->isExempted);

            return [
                'batch_id' => $first->batch_id,
                'uuid' => $first->uuid,
                'id' => $first->id,
                'total' => round($rows->sum('amount'), 2),
                'count' => $rows->count(),
                'status' => $anyExempted
                    ? 'exempted'
                    : ($rows->every(fn (Payment $p) => $p->status === Payment::STATUS_PAID) ? 'paid' : ($first->status ?? 'paid')),
                'isExempted' => $anyExempted,
                'payment_method' => $first->payment_method,
                'reference_number' => $first->reference_number,
                'paid_at' => $rows->filter(fn (Payment $p) => $p->paid_at)->max('paid_at')?->toISOString(),
                'exempted_at' => $exemptedRow?->exempted_at?->toISOString(),
                'created_at' => $last->created_at?->toISOString(),
                'academic_term' => $first->academicTerm?->displayName(),
                'user' => $first->user ? ['id' => $first->user->id, 'name' => $first->user->name, 'student_number' => $first->user->student_number] : null,
                'organization' => $first->organization ? ['id' => $first->organization->id, 'name' => $first->organization->name] : null,
                'items' => $rows->map(fn (Payment $p) => [
                    'fee_type' => $p->fee_type,
                    'amount' => (float) $p->amount,
                    'status' => $p->status,
                    'isExempted' => (bool) $p->isExempted,
                    'fee' => $p->fee ? ['id' => $p->fee->id, 'name' => $p->fee->name] : null,
                    'event' => $p->event ? ['id' => $p->event->id, 'title' => $p->event->title] : null,
                ])->values(),
            ];
        })->values()->all();
    }

    /**
     * Detailed row for the current payment batch, including exemption info,
     * processor, receipts and every item created in the same session.
     */
    private function batchDetailRow(Payment $anchor, Collection $rows): array
    {
        $rows = $rows->values();
        $first = $rows->first();
        $anyExempted = $rows->contains(fn (Payment $p) => (bool) $p->isExempted);
        $exemptedRow = $rows->first(fn (Payment $p) => (bool) $p->isExempted);

        return [
            'batch_id' => $anchor->batch_id,
            'uuid' => $anchor->uuid,
            'total' => round($rows->sum('amount'), 2),
            'count' => $rows->count(),
            'status' => $anyExempted
                ? 'exempted'
                : ($rows->every(fn (Payment $p) => $p->status === Payment::STATUS_PAID) ? 'paid' : ($first->status ?? 'paid')),
            'isExempted' => $anyExempted,
            'payment_method' => $first->payment_method,
            'reference_number' => $first->reference_number,
            'paid_at' => $rows->filter(fn (Payment $p) => $p->paid_at)->max('paid_at')?->toISOString(),
            'exempted_at' => $exemptedRow?->exempted_at?->toISOString(),
            'created_at' => $first->created_at?->toISOString(),
            'notes' => $exemptedRow?->notes,
            'exemptedBy' => $exemptedRow?->exemptedBy ? ['id' => $exemptedRow->exemptedBy->id, 'name' => $exemptedRow->exemptedBy->name] : null,
            'processedBy' => $first->processedBy ? ['id' => $first->processedBy->id, 'name' => $first->processedBy->name] : null,
            'receipts' => $rows->filter(fn (Payment $p) => $p->receipt)
                ->map(fn (Payment $p) => [
                    'id' => $p->receipt->id,
                    'receipt_number' => $p->receipt->receipt_number,
                    'issued_at' => $p->receipt->issued_at?->toISOString(),
                    'fee_type' => $p->fee_type,
                    'amount' => (float) $p->amount,
                ])
                ->values(),
            'items' => $rows->map(fn (Payment $p) => [
                'id' => $p->id,
                'fee_type' => $p->fee_type,
                'amount' => (float) $p->amount,
                'status' => $p->status,
                'isExempted' => (bool) $p->isExempted,
                'notes' => $p->notes,
                'fee' => $p->fee ? ['id' => $p->fee->id, 'name' => $p->fee->name] : null,
                'event' => $p->event ? ['id' => $p->event->id, 'title' => $p->event->title] : null,
            ])->values(),
        ];
    }
}
