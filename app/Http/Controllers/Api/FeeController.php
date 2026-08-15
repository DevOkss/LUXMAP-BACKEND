<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeeRequest;
use App\Http\Resources\FeeResource;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\FeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeController extends Controller
{
    public function __construct(
        private FeeService $feeService,
        private AccessScopeService $accessScopeService,
    ) {}

    /**
     * The student's currently-due posted fees (computed dynamically by scope
     * and required year levels), annotated with their obligation status.
     */
    public function index(Request $request)
    {
        $fees = $this->feeService->studentObligations($request->user());

        return FeeResource::collection($fees);
    }

    /**
     * The student's outstanding penalties, computed on the fly from missing
     * required QR attendances x the latest organization penalty amount.
     * Not persisted; never read from the payments table.
     */
    public function penalties(Request $request)
    {
        $obligations = app(\App\Services\PenaltyService::class)
            ->studentOutstanding($request->user());

        return response()->json(['data' => $obligations->values()]);
    }

    public function show(int $id)
    {
        $fee = $this->feeService->show($id);
        if (!$fee) {
            return response()->json(['message' => 'Fee not found'], 404);
        }

        return new FeeResource($fee);
    }

    public function store(FeeRequest $request)
    {
        $this->authorizeHead($request->user());

        $fee = $this->feeService->create($request->validated());

        return (new FeeResource($fee))->response()->setStatusCode(201);
    }

    public function update(FeeRequest $request, int $id)
    {
        $fee = $this->feeService->show($id);
        if (!$fee) {
            return response()->json(['message' => 'Fee not found'], 404);
        }

        $this->authorizeHead($request->user());
        abort_unless($this->accessScopeService->isWithinScope($request->user(), $fee->organization), 403);

        $fee = $this->feeService->update($id, $request->validated());

        return new FeeResource($fee);
    }

    public function destroy(int $id): JsonResponse
    {
        $fee = $this->feeService->show($id);
        if (!$fee) {
            return response()->json(['message' => 'Fee not found'], 404);
        }

        $this->authorizeHead(request()->user());
        abort_unless($this->accessScopeService->isWithinScope(request()->user(), $fee->organization), 403);

        $deleted = $this->feeService->delete($id);
        if (!$deleted) {
            return response()->json(['message' => 'Fee not found'], 404);
        }

        return response()->json(['message' => 'Fee deleted successfully']);
    }

    private function authorizeHead(User $user): void
    {
        abort_unless($user->hasRole(UserRole::headRoles()), 403);
    }
}
