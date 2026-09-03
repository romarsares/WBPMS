<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use Throwable;

/** Parser for the single-sheet legacy LDE daily-log workbook contract. */
final class LdeXlsDailyLogParser implements AttendanceFileParser
{
    public const VERSION = 'LDE_XLS_DAILY_LOG_V1';
    private const OLE_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    public function supports(UploadedAttendanceFile $file): bool
    {
        return strtolower(pathinfo($file->originalFilename, PATHINFO_EXTENSION)) === 'xls';
    }

    public function parse(UploadedAttendanceFile $file, ParserContext $context): ParsedAttendanceFile
    {
        $errors = $this->validateFile($file);
        if ($errors !== []) {
            throw new AttendanceParseException($errors);
        }

        try {
            $reader = new Xls();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->temporaryPath);
        } catch (Throwable) {
            throw new AttendanceParseException([new ParserError('INVALID_WORKBOOK', 'The workbook cannot be read.')]);
        }

        try {
            if ($spreadsheet->getSheetCount() !== 1) {
                throw new AttendanceParseException([new ParserError('UNEXPECTED_SHEET_COUNT', 'Exactly one worksheet is required.')]);
            }

            $sheet = $spreadsheet->getSheet(0);
            $maxRow = $sheet->getHighestDataRow();
            $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            if ($maxRow > $context->maxRows || $maxColumn > 4 + $context->maxDateColumns) {
                throw new AttendanceParseException([new ParserError('RESOURCE_LIMIT_EXCEEDED', 'The workbook exceeds the supported size.')]);
            }

            $errors = $this->findFormulas($sheet, $maxRow, $maxColumn);
            $dateColumns = $this->readHeaders($sheet, $maxColumn, $context, $errors);
            $punches = $this->readRows($sheet, $maxRow, $maxColumn, $dateColumns, $context, $errors);

            if ($errors !== []) {
                throw new AttendanceParseException($errors);
            }

            return new ParsedAttendanceFile(self::VERSION, $file->sha256, $punches);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @return list<ParserError> */
    private function validateFile(UploadedAttendanceFile $file): array
    {
        if (!$this->supports($file)) {
            return [new ParserError('INVALID_EXTENSION', 'Only .xls attendance workbooks are accepted.')];
        }
        if (!is_file($file->temporaryPath) || $file->sizeBytes < 8) {
            return [new ParserError('INVALID_WORKBOOK', 'The uploaded workbook is unavailable.')];
        }
        $signature = file_get_contents($file->temporaryPath, false, null, 0, 8);
        if ($signature !== self::OLE_SIGNATURE) {
            return [new ParserError('INVALID_OLE_SIGNATURE', 'The upload is not a legacy XLS workbook.')];
        }
        return [];
    }

    /** @param list<ParserError> $errors */
    private function readHeaders(object $sheet, int $maxColumn, ParserContext $context, array &$errors): array
    {
        $required = ['Dept', 'User ID', 'Name', 'Enroll ID'];
        foreach ($required as $index => $label) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            if (trim((string) $sheet->getCell($column . '1')->getFormattedValue()) !== $label) {
                $errors[] = new ParserError('MISSING_HEADER', "Required header {$label} is missing.", 1, $column);
            }
        }

        $columns = [];
        $dates = [];
        $contextMismatches = [];
        for ($index = 5; $index <= $maxColumn; $index++) {
            $column = Coordinate::stringFromColumnIndex($index);
            $raw = trim((string) $sheet->getCell($column . '1')->getFormattedValue());
            if ($raw === '') {
                continue;
            }
            if (!preg_match('/^(\d{1,2})\/(\d{1,2})\s+(Sun|Mon|Tue|Wed|Thu|Fri|Sat)$/', $raw, $parts)) {
                $errors[] = new ParserError('INVALID_DATE_HEADER', 'A date header is invalid.', 1, $column);
                continue;
            }
            $month = (int) $parts[1];
            $day = (int) $parts[2];
            if (!checkdate($month, $day, $context->sourceYear)) {
                $errors[] = new ParserError('INVALID_DATE_HEADER', 'A date header is invalid.', 1, $column);
                continue;
            }
            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $context->sourceYear, $month, $day), $context->timezone);
            if ($month !== $context->sourceMonth || $date->format('D') !== $parts[3]) {
                // Keep the valid column so its row values do not generate a
                // second, misleading UNEXPECTED_CELL error. Import is still
                // stopped below by one actionable source-period error.
                $columns[$index] = $date;
                $contextMismatches[] = [
                    'column' => $column,
                    'header' => $raw,
                    'month' => $month,
                    'weekday' => $parts[3],
                    'expected_weekday' => $date->format('D'),
                ];
                continue;
            }
            $key = $date->format('Y-m-d');
            if (isset($dates[$key])) {
                $errors[] = new ParserError('DUPLICATE_DATE_COLUMN', 'A date appears more than once.', 1, $column);
                continue;
            }
            $dates[$key] = true;
            $columns[$index] = $date;
        }

        if ($contextMismatches !== []) {
            $first = $contextMismatches[0];
            $selectedPeriod = (new DateTimeImmutable(sprintf(
                '%04d-%02d-01',
                $context->sourceYear,
                $context->sourceMonth,
            ), $context->timezone))->format('F Y');

            if ($first['month'] !== $context->sourceMonth) {
                $headerMonth = (new DateTimeImmutable(sprintf(
                    '%04d-%02d-01',
                    $context->sourceYear,
                    $first['month'],
                ), $context->timezone))->format('F');
                $message = sprintf(
                    'Workbook header %s is in %s, but the selected source period is %s. Select %s %d and upload again.',
                    $first['header'],
                    $headerMonth,
                    $selectedPeriod,
                    $headerMonth,
                    $context->sourceYear,
                );
            } else {
                $message = sprintf(
                    'Workbook header %s has weekday %s, but %04d-%02d-%02d is %s. Select the report year that matches the workbook and upload again.',
                    $first['header'],
                    $first['weekday'],
                    $context->sourceYear,
                    $first['month'],
                    (int) substr($first['header'], strpos($first['header'], '/') + 1, 2),
                    $first['expected_weekday'],
                );
            }

            $errors[] = new ParserError('DATE_CONTEXT_MISMATCH', $message, 1, $first['column']);
        }

        if ($columns === []) {
            $errors[] = new ParserError('INVALID_DATE_HEADER', 'At least one attendance date column is required.', 1, 'E');
        }
        return $columns;
    }

    /** @param array<int, DateTimeImmutable> $dateColumns @param list<ParserError> $errors @return list<ParsedPunch> */
    private function readRows(object $sheet, int $maxRow, int $maxColumn, array $dateColumns, ParserContext $context, array &$errors): array
    {
        $punches = [];
        $tokenCount = 0;
        for ($row = 2; $row <= $maxRow; $row++) {
            $department = $this->nullableCell($sheet, "A{$row}");
            $userId = $this->nullableCell($sheet, "B{$row}");
            $name = $this->nullableCell($sheet, "C{$row}");
            $enrollment = $this->nullableCell($sheet, "D{$row}");
            for ($index = 5; $index <= $maxColumn; $index++) {
                if (isset($dateColumns[$index])) {
                    continue;
                }
                $column = Coordinate::stringFromColumnIndex($index);
                if (trim((string) $sheet->getCell("{$column}{$row}")->getFormattedValue()) !== '') {
                    $errors[] = new ParserError('UNEXPECTED_CELL', 'A non-empty cell is outside an approved date column.', $row, $column);
                }
            }
            foreach ($dateColumns as $index => $date) {
                $column = Coordinate::stringFromColumnIndex($index);
                $raw = trim((string) $sheet->getCell("{$column}{$row}")->getFormattedValue());
                if ($raw === '') {
                    continue;
                }
                if ($enrollment === null) {
                    $errors[] = new ParserError('MISSING_ENROLLMENT_CODE', 'A punch row has no Enroll ID.', $row, 'D');
                    continue;
                }
                $tokens = preg_split('/[ \t]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (count($tokens) > $context->maxTokensPerCell) {
                    $errors[] = new ParserError('RESOURCE_LIMIT_EXCEEDED', 'A date cell has too many punch times.', $row, $column);
                    continue;
                }
                foreach ($tokens as $token) {
                    $tokenCount++;
                    if ($tokenCount > $context->maxTotalTokens) {
                        $errors[] = new ParserError('RESOURCE_LIMIT_EXCEEDED', 'The workbook has too many punch times.');
                        return $punches;
                    }
                    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $token)) {
                        $errors[] = new ParserError('INVALID_TIME_TOKEN', 'A punch time must use 24-hour HH:mm format.', $row, $column);
                        continue;
                    }
                    $punches[] = new ParsedPunch(
                        $context->deviceId,
                        $enrollment,
                        new DateTimeImmutable($date->format('Y-m-d') . " {$token}", $context->timezone),
                        $row,
                        $column,
                        $raw,
                        $department,
                        $userId,
                        $name,
                    );
                }
            }
        }
        return $punches;
    }

    /** @return list<ParserError> */
    private function findFormulas(object $sheet, int $maxRow, int $maxColumn): array
    {
        $errors = [];
        for ($row = 1; $row <= $maxRow; $row++) {
            for ($columnIndex = 1; $columnIndex <= $maxColumn; $columnIndex++) {
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                if ($sheet->getCell("{$column}{$row}")->getDataType() === DataType::TYPE_FORMULA) {
                    $errors[] = new ParserError('FORMULA_NOT_ALLOWED', 'Formula cells are not allowed.', $row, $column);
                }
            }
        }
        return $errors;
    }

    private function nullableCell(object $sheet, string $coordinate): ?string
    {
        $value = trim((string) $sheet->getCell($coordinate)->getFormattedValue());
        return $value === '' ? null : $value;
    }
}
