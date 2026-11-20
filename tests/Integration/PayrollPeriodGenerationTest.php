<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use Wbpms\Application\PayrollService;
use Wbpms\Infrastructure\Database\Connection;

final class PayrollPeriodGenerationTest extends IntegrationTestCase
{
    public function testGeneratedMonthListsOnlyItsOverlappingCutoffs(): void
    {
        $service = new PayrollService(new Connection(['pdo' => $this->pdo]));

        $result = $service->createPeriodsForMonth('2026-11');
        $periods = $service->listPeriodsForMonth('2026-11');

        $this->assertSame(5, $result['created']);
        $this->assertSame(
            ['2026-10-30', '2026-11-06', '2026-11-13', '2026-11-20', '2026-11-27'],
            array_reverse(array_column($periods, 'period_start'))
        );
        foreach ($periods as $period) {
            $this->assertLessThanOrEqual('2026-11-30', $period['period_start']);
            $this->assertGreaterThanOrEqual('2026-11-01', $period['period_end']);
        }
    }
}
