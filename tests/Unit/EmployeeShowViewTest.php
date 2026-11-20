<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EmployeeShowViewTest extends TestCase
{
    public function testStatusBadgeSharesOneVerticallyAlignedDetailRowWithItsLabel(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/hr/employees/show.php');

        self::assertIsString($view);
        self::assertStringContainsString('<div class="detail-field detail-field--status">', $view);
        self::assertStringContainsString('<span class="detail-label">Status</span>', $view);
        self::assertStringContainsString('<span class="detail-value detail-value--status"><?= $statusBadge ?></span>', $view);
        self::assertStringContainsString('.detail-field--status { align-items: center; }', $view);
        self::assertStringContainsString('.detail-value--status { display: inline-flex; align-items: center;', $view);
        self::assertStringNotContainsString("<?= \$field('Status',       null) ?>", $view);
        self::assertStringNotContainsString('style="margin-top:-.5rem"', $view);
    }
}
