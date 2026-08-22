<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceController extends Controller
{
    /**
     * Upsert the authenticated user's face enrollment. Only the descriptor is
     * stored (never the photo), encrypted at rest.
     */
    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['sometimes', 'integer'],
            // A weak template (1-2 near-identical frames) raises both false
            // accepts and false rejects — require a minimum of 3 samples.
            'descriptors' => ['required', 'array', 'min:3', 'max:5'],
            'descriptors.*' => ['array', 'size:128'],
            'descriptors.*.*' => ['numeric'],
        ]);

        $user = $request->user();

        $enrollment = FaceEnrollment::updateOrCreate(
            ['user_id' => $user->id],
            [
                'descriptors' => array_map(fn (array $descriptor) => array_map(fn ($v) => (float) $v, $descriptor), $data['descriptors']),
                'enrolled_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Face enrollment saved',
            'enrolled' => true,
            'user_id' => $enrollment->user_id,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $enrollment = FaceEnrollment::where('user_id', $request->user()->id)->first();

        return response()->json([
            'enrolled' => (bool) $enrollment,
            'descriptors' => $enrollment?->descriptors ?? [],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        FaceEnrollment::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Face enrollment removed']);
    }
}
