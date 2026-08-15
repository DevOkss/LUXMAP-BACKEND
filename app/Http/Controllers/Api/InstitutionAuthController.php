<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InstitutionAuthRequest;
use App\Services\InstitutionAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class InstitutionAuthController extends Controller
{
    public function __construct(
        private InstitutionAccountService $accountService
    ) {}

    /**
     * Mock of the institution's student portal account API.
     */
    public function authenticate(InstitutionAuthRequest $request): JsonResponse
    {
        $student = $this->accountService->authenticate(
            $request->validated('stud_id'),
            $request->validated('password')
        );

        if ($student === null) {
            throw ValidationException::withMessages([
                'stud_id' => ['Invalid student credentials.'],
            ]);
        }

        return response()->json($student);
    }
}
