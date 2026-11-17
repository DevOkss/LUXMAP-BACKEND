<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcademicTermService;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function __construct(
        private StudentImportService $importService
    ) {}

    public function create(): Response
    {
        $term = app(AcademicTermService::class)->current();

        return Inertia::render('admin/students/Import', [
            'templateUrl' => route('admin.students.import.template'),
            'currentTerm' => $term?->displayName(),
            'tempPassword' => StudentImportService::TEMP_PASSWORD,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $result = $this->importService->import($request->file('file')->getRealPath());

        $message = "Import finished: {$result['created']} created, {$result['updated']} updated, {$result['failed']} failed.";
        $flashKey = $result['failed'] > 0 ? 'error' : 'success';

        return redirect()
            ->route('admin.students.import.create')
            ->with('import_result', $result)
            ->with($flashKey, $message);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = $this->importService->buildTemplate();
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'student-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
