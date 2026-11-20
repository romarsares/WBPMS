<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EmployeeEmailConfirmationViewTest extends TestCase
{
    public function testNewEmployeeFormConfirmsTheEmailUsedForLogin(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/hr/employees/edit.php');

        self::assertIsString($view);
        self::assertStringContainsString('id="employee-form"', $view);
        self::assertStringContainsString('<?php if (!$isEdit): ?>', $view);
        self::assertStringContainsString('Please confirm that this email address is correct:', $view);
        self::assertStringContainsString('This email will be used as the employee', $view);
        self::assertStringContainsString('event.preventDefault();', $view);
    }

    public function testEmployeeEditFormDoesNotOfferTransferBranch(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/hr/employees/edit.php');

        self::assertIsString($view);
        self::assertStringNotContainsString('>Transfer Branch<', $view);
        self::assertStringNotContainsString('use <strong>Transfer Branch</strong>', $view);
    }

    public function testDailyRateIsDisplayOnlyAndDerivedFromPosition(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/hr/employees/edit.php');

        self::assertIsString($view);
        self::assertStringContainsString('type="hidden" id="daily_rate"', $view);
        self::assertStringContainsString('<output id="daily_rate_display"', $view);
        self::assertStringNotContainsString('<input type="number" id="daily_rate"', $view);
        self::assertStringContainsString('Set automatically from the selected Position / Job title.', $view);
        self::assertStringContainsString('applyPositionRate(sel)', $view);
    }
}
