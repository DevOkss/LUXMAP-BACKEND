<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportService
{
    public function __construct(
        private AttendanceService $attendanceService
    ) {}

    public function download(Event $event): StreamedResponse
    {
        $data = $this->attendanceService->getEventExportData($event);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance');

        $row = 1;
        $sheet->setCellValue([1, $row], 'EVENT NAME');
        $sheet->setCellValue([2, $row], $data['event']['title']);
        $row++;
        $sheet->setCellValue([1, $row], 'DATE');
        $sheet->setCellValue([2, $row], $data['event']['event_date']);
        $row++;
        $sheet->setCellValue([1, $row], 'TIME FROM-TO');
        $sheet->setCellValue([2, $row], trim($data['event']['time_from'] . ' - ' . $data['event']['time_to'], ' -'));
        $row += 2;

        $headerCol = 1;
        $sheet->setCellValue([$headerCol, $row], 'ID NUMBER');
        $headerCol++;
        $sheet->setCellValue([$headerCol, $row], 'NAME');
        $headerCol++;

        foreach ($data['qr_configs'] as $config) {
            $label = strtoupper($config['type']) === 'TIME_IN' ? 'TIME IN' : 'TIME OUT';
            $range = trim($this->formatTime($config['valid_from']) . ' - ' . $this->formatTime($config['valid_until']), ' -');
            $sheet->setCellValue([$headerCol, $row], "{$label} ({$range})");
            $headerCol++;
        }

        foreach ($data['rows'] as $student) {
            $row++;
            $col = 1;
            $sheet->setCellValue([$col, $row], $student['student_number']);
            $col++;
            $sheet->setCellValue([$col, $row], $student['name']);
            $col++;

            foreach ($data['qr_configs'] as $config) {
                $sheet->setCellValue([$col, $row], $student['times'][$config['id']] ?? null);
                $col++;
            }
        }

        $sheet->getStyle([1, 1, 2, 3])->getFont()->setBold(true);
        $headerRow = 5;
        $sheet->getStyle([1, $headerRow, $headerCol - 1, $headerRow])->getFont()->setBold(true);
        $sheet->getStyle([1, $headerRow, $headerCol - 1, $headerRow])
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle([1, $headerRow, $headerCol - 1, $headerRow])
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE2E8F0');

        foreach (range(1, $headerCol - 1) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $filename = Str::slug($data['event']['title'] ?: 'attendance') . '-attendance.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function formatTime(?string $time): string
    {
        if (!$time) {
            return '';
        }

        $parts = explode(':', $time);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12 ?: 12;

        return sprintf('%02d:%02d %s', $hour12, $minute, $ampm);
    }
}
