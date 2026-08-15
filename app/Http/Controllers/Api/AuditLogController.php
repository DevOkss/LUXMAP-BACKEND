<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $logs = $this->auditLogService->list($request->only([
            'user_id', 'action', 'resource_type', 'from', 'to',
        ]));

        return response()->json(AuditLogResource::collection($logs));
    }

    public function show(int $id): JsonResponse
    {
        $log = $this->auditLogService->show($id);
        if (!$log) {
            return response()->json(['message' => 'Audit log not found'], 404);
        }

        return response()->json(new AuditLogResource($log));
    }
}
