<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    public function attendance(Request $request): JsonResponse
    {
        $report = $this->reportService->attendanceReport(
            $request->input('organization_id'),
            $request->input('from'),
            $request->input('to'),
        );

        return response()->json($report);
    }

    public function financial(Request $request): JsonResponse
    {
        $report = $this->reportService->financialReport(
            $request->input('organization_id'),
            $request->input('from'),
            $request->input('to'),
        );

        return response()->json($report);
    }

    public function penalty(Request $request): JsonResponse
    {
        $report = $this->reportService->penaltyReport(
            $request->input('organization_id'),
            $request->input('from'),
            $request->input('to'),
        );

        return response()->json($report);
    }
}
