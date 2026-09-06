<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Wbpms\Application\PayrollService;
use Wbpms\Infrastructure\Database\Connection;

final class MonthlyContributionCutoffTest extends TestCase
{
    /** @dataProvider cutoffDates */
    public function testOnlyFinalFridayPaydayReceivesMonthlyContributions(string $payDate, bool $expected): void
    {
        $service = new PayrollService(new Connection([]));
        $method = new ReflectionMethod($service, 'isMonthlyContributionCutoff');

        $this->assertSame($expected, $method->invoke($service, $payDate));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function cutoffDates(): iterable
    {
        yield 'first August Friday' => ['2026-08-07', false];
        yield 'final August Friday' => ['2026-08-28', true];
        yield 'cutoff started in August but paid in early September' => ['2026-09-04', false];
        yield 'final September Friday' => ['2026-09-25', true];
    }
}
