<?php

declare(strict_types=1);

namespace Wbpms\Tests\Support;

use Wbpms\Domain\Attendance\Parsing\UploadedAttendanceFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

final class XlsFixture
{
    /** @param array<int, array{department:string,userId:string,name:string,enrollId:string,times:array<string,string>}> $rows */
    public static function make(array $rows, string $filename = 'daily-log.xls'): UploadedAttendanceFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Dept', 'User ID', 'Name', 'Enroll ID', '08/03 Mon', '08/04 Tue'], null, 'A1');
        foreach ($rows as $number => $row) {
            $target = $number + 2;
            $sheet->setCellValue("A{$target}", $row['department']);
            $sheet->setCellValue("B{$target}", $row['userId']);
            $sheet->setCellValue("C{$target}", $row['name']);
            $sheet->setCellValueExplicit("D{$target}", $row['enrollId'], DataType::TYPE_STRING);
            $sheet->setCellValue("E{$target}", $row['times']['08/03'] ?? '');
            $sheet->setCellValue("F{$target}", $row['times']['08/04'] ?? '');
        }
        $path = tempnam(sys_get_temp_dir(), 'wbpms-');
        (new Xls($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return new UploadedAttendanceFile($path, $filename, filesize($path), hash_file('sha256', $path));
    }
}
