<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports confirmed payment transactions to an .xlsx workbook. One row per
 * transaction item (each fee or penalty payment), including its receipt
 * number. Waived (exempted) transactions are included and highlighted.
 */
class PaymentExportService
{
    private const EXEMPTED_FILL = 'FFFEF3C7';

    private const HEADER_FILL = 'FFE2E8F0';

    public function __construct(
        private AccessScopeService $accessScope
    ) {}

    /**
     * @param  array{
     *   organization_ids?: array<int>,
     *   academic_term_id?: int|null,
     *   include_fees?: bool,
     *   include_penalties?: bool,
     *   fee_ids?: array<int>,
     *   event_ids?: array<int>,
     * }  $filters
     */
    public function stream(array $filters = []): StreamedResponse
    {
        $payments = $this->buildQuery($filters);

        $term = ! empty($filters['academic_term_id'])
            ? AcademicTerm::find($filters['academic_term_id'])
            : null;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transactions');

        $headers = [
            'RECEIPT NO.',
            'DATE',
            'STUDENT NAME',
            'STUDENT NO.',
            'ORGANIZATION',
            'ACADEMIC TERM',
            'TYPE',
            'DESCRIPTION',
            'AMOUNT',
            'PAYMENT METHOD',
            'REFERENCE NO.',
            'STATUS',
            'PROCESSED/EXEMPTED BY',
            'NOTES',
        ];

        $row = 1;
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, $row], $header);
        }

        $sheet->getStyle([1, $row, count($headers), $row])->getFont()->setBold(true);
        $sheet->getStyle([1, $row, count($headers), $row])
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle([1, $row, count($headers), $row])
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::HEADER_FILL);

        foreach ($payments as $payment) {
            $row++;
            $this->writeRow($sheet, $row, $payment);
        }

        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
        $sheet->getStyle([1, 1, count($headers), max($row, 1)])
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP);

        $filename = 'payments-' . ($term ? Str::slug($term->displayName()) : 'all-terms') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildQuery(array $filters): Collection
    {
        $query = Payment::with([
            'user', 'organization', 'academicTerm', 'fee',
            'event', 'receipt', 'exemptedBy', 'processedBy',
        ]);

        if (! empty($filters['organization_ids'])) {
            $query->whereIn('organization_id', $filters['organization_ids']);
        }
        if (! empty($filters['academic_term_id'])) {
            $query->where('academic_term_id', $filters['academic_term_id']);
        }

        $includeFees = ! empty($filters['include_fees']);
        $includePenalties = ! empty($filters['include_penalties']);
        $feeIds = array_filter($filters['fee_ids'] ?? []);
        $eventIds = array_filter($filters['event_ids'] ?? []);

        if ($includeFees && $includePenalties) {
            $query->where(function ($q) use ($feeIds, $eventIds) {
                if (! empty($feeIds)) {
                    $q->orWhere(fn ($sub) => $sub->where('fee_type', Payment::TYPE_FEE)->whereIn('fee_id', $feeIds));
                } else {
                    $q->orWhere('fee_type', Payment::TYPE_FEE);
                }

                if (! empty($eventIds)) {
                    $q->orWhere(fn ($sub) => $sub->where('fee_type', Payment::TYPE_PENALTY)->whereIn('event_id', $eventIds));
                } else {
                    $q->orWhere('fee_type', Payment::TYPE_PENALTY);
                }
            });
        } elseif ($includeFees) {
            $query->where('fee_type', Payment::TYPE_FEE)
                ->when(! empty($feeIds), fn ($q) => $q->whereIn('fee_id', $feeIds));
        } elseif ($includePenalties) {
            $query->where('fee_type', Payment::TYPE_PENALTY)
                ->when(! empty($eventIds), fn ($q) => $q->whereIn('event_id', $eventIds));
        }

        return $query->orderBy('created_at')->get();
    }

    private function writeRow($sheet, int $row, Payment $payment): void
    {
        $exempted = (bool) $payment->isExempted || $payment->status === Payment::STATUS_EXEMPTED;

        $type = $payment->fee_type === Payment::TYPE_PENALTY ? 'Penalty' : 'Fee';
        $description = $payment->fee?->name
            ?? $payment->event?->title
            ?? ($type === 'Penalty' ? 'Penalty' : 'Fee');

        $method = match ($payment->payment_method) {
            Payment::METHOD_CASHLESS => 'Cashless',
            Payment::METHOD_EXEMPTION => 'Exemption',
            default => 'Cash',
        };

        $status = match (true) {
            $exempted => 'Exempted',
            $payment->status === Payment::STATUS_PAID => 'Paid',
            $payment->status === Payment::STATUS_REFUNDED => 'Refunded',
            default => Str::title($payment->status ?? ''),
        };

        $issuedBy = $payment->exemptedBy?->name
            ?? $payment->processedBy?->name
            ?? $payment->receipt?->issuedBy?->name
            ?? null;

        $column = 1;
        $sheet->setCellValue([$column++, $row], $payment->receipt?->receipt_number ?? '');
        $sheet->setCellValue([$column++, $row], $payment->created_at?->format('Y-m-d H:i:s'));
        $sheet->setCellValue([$column++, $row], $payment->user?->name ?? '');
        $sheet->setCellValue([$column++, $row], $payment->user?->student_number ?? '');
        $sheet->setCellValue([$column++, $row], $payment->organization?->name ?? '');
        $sheet->setCellValue([$column++, $row], $payment->academicTerm?->displayName() ?? '');
        $sheet->setCellValue([$column++, $row], $type);
        $sheet->setCellValue([$column++, $row], $description);
        $sheet->setCellValue([$column++, $row], (float) $payment->amount);
        $sheet->setCellValue([$column++, $row], $method);
        $sheet->setCellValue([$column++, $row], $payment->reference_number ?? '');
        $sheet->setCellValue([$column++, $row], $status);
        $sheet->setCellValue([$column++, $row], $issuedBy ?? '');
        $sheet->setCellValue([$column, $row], $payment->notes ?? '');

        if ($exempted) {
            $sheet->getStyle([1, $row, 14, $row])
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB(self::EXEMPTED_FILL);
        }
    }
}