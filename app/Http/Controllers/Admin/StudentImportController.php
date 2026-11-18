<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcademicTermService;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $path = $request->file('file')->getRealPath();

        return response()->stream(function () use ($path) {
            $streaming = ! app()->runningUnitTests();

            if ($streaming) {
                @ini_set('zlib.output_compression', '0');

                while (ob_get_level() > 0 && @ob_end_flush()) {
                    // Flush any framework/PHP buffers so progress lines reach the browser.
                }
            }

            $emit = function (array $payload) use ($streaming): void {
                echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

                if ($streaming) {
                    @ob_flush();
                    flush();
                }
            };

            try {
                $result = $this->importService->import($path, function (int $processed, int $total) use ($emit) {
                    $emit([
                        'type' => 'progress',
                        'processed' => $processed,
                        'total' => $total,
                        'percent' => $total > 0 ? min(100, (int) floor($processed / $total * 100)) : 0,
                    ]);
                });

                $emit(['type' => 'result', ...$result]);
            } catch (ValidationException $e) {
                $emit([
                    'type' => 'error',
                    'message' => collect($e->errors())->flatten()->first() ?? 'Import failed.',
                ]);
            } catch (\Throwable $e) {
                report($e);
                $emit(['type' => 'error', 'message' => 'Import failed. Please try again.']);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
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
