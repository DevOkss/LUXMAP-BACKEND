<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\AcademicTermService;
use App\Services\AccessScopeService;
use App\Services\EligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScopeService,
        private AcademicTermService $terms,
        private EligibilityService $eligibility
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $term = $this->resolveTerm((int) $request->query('academic_term_id', 0))
            ?? $this->terms->current();

        $currentTerm = $this->terms->current();
        // The green card must track the selected term, not always the active one
        $displayTerm = $term ?? $currentTerm;

        // Scope is term-aware for officers (head/officer assignments filtered by term)
        $scopeOrgs = $this->accessScopeService->scopeOrganizations($user, $term);
        $orgIds = $scopeOrgs->pluck('id')->all();

        $sessionOrgId = session('current_organization_id');
        if ($sessionOrgId && in_array($sessionOrgId, $orgIds, true)) {
            $orgIds = [$sessionOrgId];
            $scopeOrgs = Organization::query()->whereIn('id', $orgIds)->get();
        }

        // Income: only settled money (status = paid). Exemptions are the
        // amount waived instead of collected.
        $totalIncome = $this->paidQuery($orgIds, $term)->sum('amount');
        $exemptedAmount = (float) Payment::exempted()
            ->when(count($orgIds), fn ($q) => $q->whereIn('organization_id', $orgIds))
            ->when($term, fn ($q) => $q->where('academic_term_id', $term->id))
            ->sum('amount');

        $incomeChart = $this->monthlySeries($orgIds, $term);

        $breakdown = ['fees' => 0.0, 'penalties' => 0.0];
        foreach ($this->paidQuery($orgIds, $term)->get(['fee_type', 'amount']) as $payment) {
            $breakdown[$payment->fee_type === Payment::TYPE_PENALTY ? 'penalties' : 'fees'] += (float) $payment->amount;
        }

        $staffRoles = array_map(fn (UserRole $role) => $role->value, UserRole::staffRoles());

        return Inertia::render('admin/Dashboard/Index', [
            'terms' => $this->academicTermsForPicker(),
            'selected_term' => $term?->id ?? null,
            'current_term' => $displayTerm ? [
                'id' => $displayTerm->id,
                'name' => $displayTerm->displayName(),
                'start_date' => $displayTerm->start_date?->format('Y-m-d'),
                'end_date' => $displayTerm->end_date?->format('Y-m-d'),
                'is_active' => $displayTerm->is_active,
            ] : null,
            'active_term_id' => $currentTerm?->id ?? null,
            'scope_orgs' => $scopeOrgs
                ->sortBy(fn (Organization $org) => $org->type->value)
                ->map(fn (Organization $org) => $this->organizationShape($org))
                ->values()
                ->all(),
            'stats' => [
                'total_income' => round((float) $totalIncome, 2),
                'exempted_amount' => round($exemptedAmount, 2),
                'total_students' => $this->distinctStudentCount($scopeOrgs, $term),
                'total_officers' => $this->distinctOfficerCount($scopeOrgs, $staffRoles, $term),
                'pending_verifications' => PaymentSubmission::pending()
                    ->when(count($orgIds), fn ($q) => $q->whereIn('organization_id', $orgIds))
                    ->count(),
            ],
            'income_chart' => $incomeChart,
            'income_breakdown' => $breakdown,
            'org_breakdown' => $scopeOrgs
                ->sortBy('id')
                ->map(fn (Organization $org) => [
                    'organization' => $this->organizationShape($org),
                    'students' => $this->eligibility->studentIds($org, [], $term)->count(),
                    'officers' => $this->officerCount($org, $staffRoles, $term),
                ])
                ->values()
                ->all(),
            'recent_payments' => Payment::with('user')
                ->when(count($orgIds), fn ($q) => $q->whereIn('organization_id', $orgIds))
                ->when($term, fn ($q) => $q->where('academic_term_id', $term->id))
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'status' => $p->status,
                    'paid_at' => $p->paid_at?->toISOString(),
                    'user' => $p->user ? ['name' => $p->user->name] : null,
                ]),
            'upcoming_events' => Event::with('organization:id,code,name,type')
                ->when(count($orgIds), fn ($q) => $q->whereIn('organization_id', $orgIds))
                ->where('status', 'published')
                ->upcoming()
                ->orderBy('event_date')
                ->take(5)
                ->get()
                ->map(fn (Event $event) => [
                    'uuid' => $event->uuid,
                    'title' => $event->title,
                    'event_date' => $event->event_date?->format('Y-m-d'),
                    'venue' => $event->venue,
                    'organization' => $event->organization
                        ? ['id' => $event->organization->id, 'code' => $event->organization->code, 'name' => $event->organization->name]
                        : null,
                ]),
            'currentRoute' => '/dashboard',
        ]);
    }

    private function paidQuery(array $orgIds, ?AcademicTerm $term)
    {
        return Payment::paid()
            ->when(count($orgIds), fn ($q) => $q->whereIn('organization_id', $orgIds))
            ->when($term, fn ($q) => $q->where('academic_term_id', $term->id));
    }

    /**
     * Monthly paid-income series for the selected term. Grouping happens in
     * PHP (not DB date functions) so SQLite tests and MySQL agree.
     *
     * @return array<int, array{month: string, amount: float, highlight: bool}>
     */
    private function monthlySeries(array $orgIds, ?AcademicTerm $term): array
    {
        $sums = $this->paidQuery($orgIds, $term)
            ->get(['amount', 'paid_at'])
            ->reduce(function (array $carry, Payment $p) {
                $key = $p->paid_at?->format('Y-m');
                if ($key) {
                    $carry[$key] = ($carry[$key] ?? 0) + (float) $p->amount;
                }

                return $carry;
            }, []);

        $axis = $this->seriesAxis($term, array_keys($sums));
        $now = now();

        return array_map(fn (string $ym) => [
            'month' => Carbon::parse($ym.'-01')->shortMonthName,
            'income' => round($sums[$ym] ?? 0, 2),
            'highlight' => $term?->is_active && $ym === $now->format('Y-m'),
        ], $axis);
    }

    /**
     * Month keys ('Y-m') for the chart axis: the months inside the term's
     * start/end window when available, otherwise the months present in the
     * data (falling back to the current month for an empty term).
     */
    private function seriesAxis(?AcademicTerm $term, array $dataMonths): array
    {
        if ($term && $term->start_date && $term->end_date) {
            $axis = [];
            $cursor = $term->start_date->copy()->startOfMonth();
            $end = $term->end_date->copy()->startOfMonth();

            while ($cursor->lte($end) && count($axis) < 24) {
                $axis[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            if ($axis !== []) {
                return $axis;
            }
        }

        $axis = array_values(array_unique($dataMonths));
        sort($axis);

        return $axis !== [] ? $axis : [now()->format('Y-m')];
    }

    private function distinctStudentCount(Collection $scopeOrgs, ?AcademicTerm $term): int
    {
        $ids = collect();

        foreach ($scopeOrgs as $org) {
            $ids = $ids->merge($this->eligibility->studentIds($org, [], $term));
        }

        return $ids->unique()->count();
    }

    private function officerCount(Organization $org, array $roles, ?AcademicTerm $term = null): int
    {
        return $this->officerQuery([$org->id], $roles, $term)->count();
    }

    private function distinctOfficerCount(Collection $orgs, array $roles, ?AcademicTerm $term = null): int
    {
        if ($orgs->isEmpty()) {
            return 0;
        }

        return $this->officerQuery($orgs->pluck('id')->all(), $roles, $term)->count();
    }

    /**
     * Users holding a staff officer role in the given organizations.
     * Officers are the staff roles only (heads are not counted as their own
     * org's officers), matching the Officers management module.
     * When $term is provided the query is term-aware (officer must be assigned
     * for that term). Legacy rows with NULL term are treated as belonging to
     * any term for backward compatibility (tests create officers without term).
     */
    private function officerQuery(array $orgIds, array $roles, ?AcademicTerm $term = null)
    {
        return User::query()
            ->whereNull('deleted_at')
            ->whereIn('id', function ($query) use ($orgIds, $roles, $term) {
                $query->select('user_id')
                    ->from('organization_user')
                    ->when($orgIds !== [], fn ($q) => $q->whereIn('organization_id', $orgIds))
                    ->whereIn('role', $roles)
                    ->when($term, fn ($q) => $q->where(function ($qq) use ($term) {
                        $qq->where('academic_term_id', $term->id)->orWhereNull('academic_term_id');
                    }))
                    ->distinct();
            });
    }

    private function organizationShape(Organization $org): array
    {
        return [
            'id' => $org->id,
            'code' => $org->code,
            'name' => $org->name,
            'type' => $org->type->value,
        ];
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
}