<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectSubmissionRequest;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\PaymentSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PaymentSubmissionController extends Controller
{
    public function __construct(
        private PaymentSubmissionService $submissions
    ) {}

    /**
     * Verification detail of a pending cashless submission group.
     */
    public function show(Request $request, string $groupKey)
    {
        $user = $request->user();
        $rows = $this->submissions->groupRows($groupKey);

        if ($rows->isEmpty()) {
            return redirect()->route('admin.payments.index', ['tab' => 'pending'])->with('error', 'Submission group not found.');
        }

        $first = $rows->first();

        if ($first->organization && $first->organization_id !== null
            && !in_array($first->organization_id, $this->scopedOrgIds($user), true)) {
            abort(403, 'This submission is outside your scope.');
        }

        $canVerify = $first->organization
            && in_array($user->roleInOrganization($first->organization), UserRole::staffRoles(), true);

        return Inertia::render('admin/payments/SubmissionDetail', [
            'submission' => [
                'group_key' => $first->group_key,
                'status' => $first->status,
                'rejection_reason' => $first->rejection_reason,
                'reference_number' => $first->reference_number,
                'payment_channel' => $first->payment_channel,
                'receipt_image_url' => $first->receipt_image ? Storage::url($first->receipt_image) : null,
                'submitted_at' => $first->created_at,
                'verified_at' => $first->verified_at,
                'academic_term' => $first->academicTerm?->displayName(),
                'student' => $first->user ? ['id' => $first->user->id, 'name' => $first->user->name, 'student_number' => $first->user->student_number] : null,
                'organization' => $first->organization ? ['id' => $first->organization->id, 'name' => $first->organization->name] : null,
                'verifiedBy' => $first->verifiedBy ? ['id' => $first->verifiedBy->id, 'name' => $first->verifiedBy->name] : null,
                'total' => round($rows->sum('amount'), 2),
                'items' => $rows->map(fn ($row) => [
                    'fee_type' => $row->fee_type,
                    'amount' => (float) $row->amount,
                    'status' => $row->status,
                    'fee' => $row->fee ? ['id' => $row->fee->id, 'name' => $row->fee->name] : null,
                    'event' => $row->event ? ['id' => $row->event->id, 'title' => $row->event->title] : null,
                ])->values(),
            ],
            'can_verify' => $canVerify,
        ]);
    }

    public function approve(Request $request, string $groupKey)
    {
        try {
            $payments = $this->submissions->approve($request->user(), $groupKey);
        } catch (ValidationException $e) {
            return redirect()->route('admin.payments.submissions.show', $groupKey)
                ->with('error', $e->errors()['submission'][0] ?? 'Unable to approve this submission.');
        }

        return redirect()->route('admin.payments.index', ['tab' => 'pending'])
            ->with('success', 'Payment approved. ' . count($payments) . ' transaction(s) recorded.');
    }

    public function reject(RejectSubmissionRequest $request, string $groupKey)
    {
        $this->submissions->reject($request->user(), $groupKey, $request->input('rejection_reason'));

        return redirect()->route('admin.payments.index', ['tab' => 'pending'])
            ->with('success', 'Submission rejected.');
    }

    private function scopedOrgIds(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return \App\Models\Organization::query()->pluck('id')->all();
        }

        return app(\App\Services\AccessScopeService::class)->scopeOrganizationIds($user);
    }
}