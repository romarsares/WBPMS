<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit\Attendance\Parsing;

use Wbpms\Domain\Attendance\Parsing\AttendanceParseException;
use Wbpms\Domain\Attendance\Parsing\LdeXlsDailyLogParser;
use Wbpms\Domain\Attendance\Parsing\ParserContext;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Wbpms\Tests\Support\XlsFixture;

final class LdeXlsDailyLogParserTest extends TestCase
{
    public function testItPreservesLeadingZerosAndExpandsEveryTimeToken(): void
    {
        $file = XlsFixture::make([['department' => 'Office', 'userId' => '5', 'name' => 'Ana Cruz', 'enrollId' => '00123', 'times' => ['08/03' => '08:01 17:10', '08/04' => '08:00']]]);
        try {
            $parsed = (new LdeXlsDailyLogParser())->parse($file, new ParserContext(1, 2026, 8, new DateTimeZone('Asia/Manila')));
            self::assertSame(LdeXlsDailyLogParser::VERSION, $parsed->parserVersion);
            self::assertCount(3, $parsed->punches);
            self::assertSame('00123', $parsed->punches[0]->enrollmentCode);
            self::assertSame('2026-08-03 08:01', $parsed->punches[0]->localTimestamp->format('Y-m-d H:i'));
        } finally {
            @unlink($file->temporaryPath);
        }
    }

    public function testItReturnsStableErrorForBadTimeToken(): void
    {
        $file = XlsFixture::make([['department' => 'Office', 'userId' => '5', 'name' => 'Ana Cruz', 'enrollId' => '00123', 'times' => ['08/03' => '8:01']]]);
        try {
            try {
                (new LdeXlsDailyLogParser())->parse($file, new ParserContext(1, 2026, 8));
                self::fail('Expected parser validation failure.');
            } catch (AttendanceParseException $exception) {
                self::assertSame('INVALID_TIME_TOKEN', $exception->errors[0]->code);
                self::assertSame(2, $exception->errors[0]->row);
                self::assertSame('E', $exception->errors[0]->column);
            }
        } finally {
            @unlink($file->temporaryPath);
        }
    }

    public function testItReportsOneActionableErrorForTheWrongSelectedMonth(): void
    {
        $file = XlsFixture::make([['department' => 'Office', 'userId' => '5', 'name' => 'Ana Cruz', 'enrollId' => '00123', 'times' => ['08/03' => '08:01', '08/04' => '17:10']]]);
        try {
            try {
                (new LdeXlsDailyLogParser())->parse($file, new ParserContext(1, 2026, 9));
                self::fail('Expected a source-period validation failure.');
            } catch (AttendanceParseException $exception) {
                self::assertCount(1, $exception->errors);
                self::assertSame('DATE_CONTEXT_MISMATCH', $exception->errors[0]->code);
                self::assertSame('E', $exception->errors[0]->column);
                self::assertStringContainsString('August', $exception->errors[0]->message);
                self::assertStringContainsString('September 2026', $exception->errors[0]->message);
            }
        } finally {
            @unlink($file->temporaryPath);
        }
    }
}
