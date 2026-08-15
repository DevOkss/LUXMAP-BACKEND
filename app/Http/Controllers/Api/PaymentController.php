<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentSubmissionRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Services\AcademicTermService;
use App\Services\ObligationService;
use App\Services\PaymentService;
use App\Services\PaymentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private ObligationService $obligations,
        private PaymentSubmissionService $submissions,
        private AcademicTermService $terms
    ) {}

    /**
     * The student's confirmed transactions (transaction history).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $filters = $request->only(['status', 'payment_method', 'fee_type']);
        $filters['user_id'] = $user->id;

        return PaymentResource::collection($this->paymentService->list($filters));
    }

    public function show(Request $request, int $id)
    {
        $payment = $this->paymentService->show($id);

        if (!$payment || $payment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return new PaymentResource($payment);
    }

    /**
     * Dynamically computed outstanding obligations + the organization's
     * official payment account for each involved organization.
     */
    public function outstanding(Request $request): JsonResponse
    {
        $user = $request->user();
        $term = $this->terms->current();

        $data = $this->obligations->forUser($user, null, $term);

        $organizationIds = collect($data['fees'])
            ->pluck('organization.id')
            ->concat($data['penalties']->pluck('event.organization.id'))
            ->filter()
            ->unique();

        $accounts = PaymentAccount::with('organization')
            ->active()
            ->whereIn('organization_id', $organizationIds)
            ->get();

        return response()->json([
            ...$data,
            'unresolved' => $this->submissions->unresolvedKeys($user),
            'payment_accounts' => $accounts->map(fn (PaymentAccount $account) => [
                'organization_id' => $account->organization_id,
                'organization_name' => $account->organization?->name,
                'account_name' => $account->account_name,
                'account_provider' => $account->account_provider,
                'account_number' => $account->account_number,
                'qr_code_image_url' => $account->qr_code_image ? '/storage/'.$account->qr_code_image : null,
            ])->values(),
        ]);
    }

    /**
     * Submit a cashless payment attempt (receipt + reference) for one or more
     * outstanding obligations of the student's organization.
     */
    public function submit(StorePaymentSubmissionRequest $request)
    {
        $result = $this->submissions->submit($request->user(), $request->validated());

        return response()->json($result, 201);
    }

    /**
     * The student's payment submissions, grouped per attempt.
     */
    public function submissions(Request $request): JsonResponse
    {
        $groups = $this->submissions->userGroups($request->user());

        $payload = $groups->map(function ($rows) {
            $group = [
                'group_key' => $rows->first()->group_key,
                'status' => $rows->first()->status,
                'rejection_reason' => $rows->first()->rejection_reason,
                'reference_number' => $rows->first()->reference_number,
                'receipt_image_url' => $rows->first()->receipt_image ? '/storage/'.$rows->first()->receipt_image : null,
                'payment_channel' => $rows->first()->payment_channel,
                'organization' => $rows->first()->organization ? [
                    'id' => $rows->first()->organization->id,
                    'name' => $rows->first()->organization->name,
                ] : null,
                'academic_term' => $rows->first()->academicTerm?->displayName(),
                'verified_at' => $rows->first()->verified_at,
                'submitted_at' => $rows->first()->created_at,
                'items' => $rows->map(fn ($row) => [
                    'fee_type' => $row->fee_type,
                    'amount' => (float) $row->amount,
                    'fee' => $row->fee ? ['id' => $row->fee->id, 'name' => $row->fee->name] : null,
                    'event' => $row->event ? ['id' => $row->event->id, 'title' => $row->event->title] : null,
                    'status' => $row->status,
                ])->values(),
            ];

            return $group;
        })->values();

        return response()->json(['data' => $payload]);
    }

    /**
     * Detail of one submission attempt (group).
     */
    public function submissionDetail(Request $request, string $groupKey): JsonResponse
    {
        $user = $request->user();
        $rows = $this->submissions->groupRows($groupKey)->filter(fn ($row) => $row->user_id === $user->id);

        if ($rows->isEmpty()) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        return response()->json([
            'group_key' => $rows->first()->group_key,
            'status' => $rows->first()->status,
            'rejection_reason' => $rows->first()->rejection_reason,
            'reference_number' => $rows->first()->reference_number,
            'receipt_image_url' => $rows->first()->receipt_image ? '/storage/'.$rows->first()->receipt_image : null,
            'organization' => $rows->first()->organization ? ['id' => $rows->first()->organization->id, 'name' => $rows->first()->organization->name] : null,
            'academic_term' => $rows->first()->academicTerm?->displayName(),
            'verified_at' => $rows->first()->verified_at,
            'submitted_at' => $rows->first()->created_at,
            'items' => $rows->map(fn ($row) => [
                'fee_type' => $row->fee_type,
                'amount' => (float) $row->amount,
                'fee' => $row->fee ? ['id' => $row->fee->id, 'name' => $row->fee->name] : null,
                'event' => $row->event ? ['id' => $row->event->id, 'title' => $row->event->title] : null,
                'status' => $row->status,
            ])->values(),
        ]);
    }
}