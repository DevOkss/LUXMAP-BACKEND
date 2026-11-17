<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StudentImportService
{
    public const TEMP_PASSWORD = 'student';

    /**
     * Canonical column => accepted header aliases (already normalized).
     */
    private const COLUMN_ALIASES = [
        'student_number' => ['student number', 'student no', 'student id', 'id number', 'id no'],
        'name' => ['name', 'full name', 'student name'],
        'email' => ['email', 'email address'],
        'phone' => ['phone', 'phone number', 'contact', 'contact number', 'mobile', 'mobile number'],
        'year_level' => ['year level', 'year'],
        'sex' => ['sex', 'gender'],
        'is_enrolled' => ['is enrolled', 'enrolled'],
        'institute' => ['institute', 'institute code', 'institute name', 'college'],
        'program' => ['program', 'program code', 'program name', 'course'],
    ];

    public function __construct(
        private AcademicTermService $academicTermService
    ) {}

    /**
     * Import students from an uploaded spreadsheet.
     *
     * Existing accounts are matched by student number and updated without
     * touching their password. New accounts receive the temporary password and
     * must change it on first login.
     *
     * @return array{created: int, updated: int, failed: int, rows: int, errors: array, term: ?string}
     */
    public function import(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file could not be read as a spreadsheet.'],
            ]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        $headerMap = $this->resolveHeaderMap(
            $sheet->rangeToArray('A1:'.$highestColumn.'1', null, true, true)[0] ?? []
        );

        $instituteLookup = $this->instituteLookup();
        $programs = Program::all();
        $term = $this->academicTermService->current();

        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = [];
        $seenStudentNumbers = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $sheet->rangeToArray(
                'A'.$rowNumber.':'.$highestColumn.$rowNumber,
                null,
                true,
                true
            )[0] ?? [];

            $values = [];

            foreach ($headerMap as $column => $index) {
                $values[$column] = trim((string) ($row[$index] ?? ''));
            }

            if (collect($values)->every(fn (string $value) => $value === '')) {
                continue;
            }

            $processed++;
            $studentNumber = $values['student_number'];

            if ($studentNumber === '') {
                $errors[] = $this->rowError($rowNumber, null, 'Student number is required.');

                continue;
            }

            if (isset($seenStudentNumbers[$studentNumber])) {
                $errors[] = $this->rowError($rowNumber, $studentNumber, 'Duplicate student number in the file.');

                continue;
            }

            $seenStudentNumbers[$studentNumber] = true;

            $institute = $instituteLookup[$this->normalize($values['institute'])] ?? null;

            if (! $institute) {
                $errors[] = $this->rowError(
                    $rowNumber,
                    $studentNumber,
                    $values['institute'] === ''
                        ? 'Institute is required.'
                        : "Institute [{$values['institute']}] was not found."
                );

                continue;
            }

            $program = $this->resolveProgram($programs, $institute, $values['program']);

            if (! $program) {
                $errors[] = $this->rowError(
                    $rowNumber,
                    $studentNumber,
                    $values['program'] === ''
                        ? 'Program is required.'
                        : "Program [{$values['program']}] does not belong to institute [{$institute->name}]."
                );

                continue;
            }

            $sex = $this->parseSex($values['sex']);

            if ($sex === false) {
                $errors[] = $this->rowError($rowNumber, $studentNumber, 'Sex must be M or F.');

                continue;
            }

            $isEnrolled = $this->parseBoolean($values['is_enrolled']);

            if ($isEnrolled === null) {
                $errors[] = $this->rowError($rowNumber, $studentNumber, 'Is enrolled must be 1/0 or Yes/No.');

                continue;
            }

            $yearLevel = $this->parseYearLevel($values['year_level']);

            if ($yearLevel === false) {
                $errors[] = $this->rowError($rowNumber, $studentNumber, 'Year level must be a number between 1 and 6.');

                continue;
            }

            $email = strtolower($values['email']);
            $validator = Validator::make([
                'name' => $values['name'],
                'email' => $email,
                'phone' => $values['phone'],
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
            ]);

            if ($validator->fails()) {
                $errors[] = $this->rowError(
                    $rowNumber,
                    $studentNumber,
                    implode(' ', $validator->errors()->all())
                );

                continue;
            }

            $user = User::withTrashed()->where('student_number', $studentNumber)->first();

            if ($user && ($user->isSuperAdmin() || $user->hasOfficerRole())) {
                $errors[] = $this->rowError(
                    $rowNumber,
                    $studentNumber,
                    'This student number belongs to a staff account and cannot be imported.'
                );

                continue;
            }

            $emailTaken = User::withTrashed()
                ->where('email', $email)
                ->when($user, fn ($query) => $query->where('id', '!=', $user->id))
                ->exists();

            if ($emailTaken) {
                $errors[] = $this->rowError(
                    $rowNumber,
                    $studentNumber,
                    "Email [{$email}] is already used by another account."
                );

                continue;
            }

            try {
                DB::transaction(function () use (
                    $user,
                    $studentNumber,
                    $values,
                    $email,
                    $sex,
                    $isEnrolled,
                    $yearLevel,
                    $institute,
                    $program,
                    $term,
                    &$created,
                    &$updated
                ) {
                    $attributes = [
                        'name' => $values['name'],
                        'email' => $email,
                        'phone' => $values['phone'] !== '' ? $values['phone'] : null,
                        'year_level' => $yearLevel,
                        'sex' => $sex,
                        'is_enrolled' => $isEnrolled,
                        'institute_id' => $institute->id,
                        'program_id' => $program->id,
                    ];

                    if ($user) {
                        if ($user->trashed()) {
                            $user->restore();
                        }

                        $user->update($attributes);
                        $updated++;
                    } else {
                        $user = User::create([
                            'student_number' => $studentNumber,
                            ...$attributes,
                            'password' => Hash::make(self::TEMP_PASSWORD),
                            'must_change_password' => true,
                        ]);
                        $created++;
                    }

                    if ($term) {
                        $this->academicTermService->syncEnrollment($user, $term, [
                            'is_enrolled' => $isEnrolled,
                            'year_level' => $yearLevel,
                            'institute_id' => $institute->id,
                            'program_id' => $program->id,
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                report($e);
                $errors[] = $this->rowError(
                    $rowNumber,
                    $studentNumber,
                    'This row could not be saved.'
                );
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed' => count($errors),
            'rows' => $processed,
            'errors' => array_slice($errors, 0, 50),
            'term' => $term?->displayName(),
        ];
    }

    public function buildTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Students');

        $headers = [
            'student_number', 'name', 'email', 'phone',
            'year_level', 'sex', 'is_enrolled', 'institute', 'program',
        ];

        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DCFCE7'],
            ],
        ]);

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Student Import Instructions'],
            [],
            ['1. Enter student data on the "Students" sheet only, starting at row 2.'],
            ['2. Do not rename or reorder the header columns.'],
            ['3. Institute and program accept either the code or the full name.'],
            ['4. Format the student_number column as Text if the values have leading zeros.'],
            ['5. Existing accounts are matched by student number; passwords are never changed.'],
            ['6. New accounts receive the temporary password "student" and must change it on first login.'],
            ['7. Year level accepts 1-6 or 1st-6th. Sex accepts M/F or Male/Female.'],
            ['8. Is enrolled accepts 1/0, Yes/No, or True/False.'],
            [],
            ['Example row (do not include this row in the Students sheet)'],
            $headers,
            ['2026-00001', 'Juan Dela Cruz', 'juan@example.com', '09171234567', 1, 'M', 1, 'ICS', 'BSCS'],
        ], null, 'A1');

        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instructions->getStyle('A12:I13')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(80);
        $instructions->getColumnDimension('B')->setWidth(22);
        $instructions->getColumnDimension('C')->setWidth(22);
        $instructions->getColumnDimension('D')->setWidth(18);
        $instructions->getColumnDimension('E')->setWidth(12);
        $instructions->getColumnDimension('F')->setWidth(10);
        $instructions->getColumnDimension('G')->setWidth(12);
        $instructions->getColumnDimension('H')->setWidth(12);
        $instructions->getColumnDimension('I')->setWidth(12);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function resolveHeaderMap(array $headerRow): array
    {
        $headerMap = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalize((string) $header);

            if ($normalized === '') {
                continue;
            }

            foreach (self::COLUMN_ALIASES as $column => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $headerMap[$column] = $index;
                    break;
                }
            }
        }

        $missing = array_diff(array_keys(self::COLUMN_ALIASES), array_keys($headerMap));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => ['The file is missing required column(s): '.implode(', ', $missing).'.'],
            ]);
        }

        return $headerMap;
    }

    /**
     * @return array<string, Institute>
     */
    private function instituteLookup(): array
    {
        $lookup = [];

        foreach (Institute::orderBy('name')->get() as $institute) {
            foreach ([$institute->code, $institute->name] as $value) {
                $key = $this->normalize($value);

                if ($key !== '' && ! isset($lookup[$key])) {
                    $lookup[$key] = $institute;
                }
            }
        }

        return $lookup;
    }

    private function resolveProgram($programs, Institute $institute, string $value): ?Program
    {
        $key = $this->normalize($value);

        if ($key === '') {
            return null;
        }

        return $programs
            ->filter(fn (Program $program) => $program->institute_id === $institute->id)
            ->first(fn (Program $program) => $this->normalize($program->code) === $key
                || $this->normalize($program->name) === $key);
    }

    private function parseBoolean(string $value): ?bool
    {
        return match (strtolower($value)) {
            '1', 'true', 'yes', 'y', 'enrolled', 'active' => true,
            '0', 'false', 'no', 'n', 'not enrolled', 'unenrolled', 'inactive' => false,
            default => null,
        };
    }

    private function parseSex(string $value): string|false|null
    {
        return match (strtoupper($value)) {
            '', 'N/A', 'NA' => null,
            'M', 'MALE', 'MAN' => 'M',
            'F', 'FEMALE', 'WOMAN' => 'F',
            default => false,
        };
    }

    private function parseYearLevel(string $value): int|false|null
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\s*([1-6])(?:st|nd|rd|th)?(?:\s*year)?\s*$/i', $value, $matches)) {
            return (int) $matches[1];
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    /**
     * @return array{row: int, student_number: ?string, message: string}
     */
    private function rowError(int $row, ?string $studentNumber, string $message): array
    {
        return [
            'row' => $row,
            'student_number' => $studentNumber,
            'message' => $message,
        ];
    }
}
