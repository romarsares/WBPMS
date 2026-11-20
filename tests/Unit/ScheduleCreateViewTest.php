<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ScheduleCreateViewTest extends TestCase
{
    public function testNewScheduleModalKeepsCancelButRemovesRedundantCloseButton(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/hr/schedules/index.php');

        self::assertIsString($view);
        self::assertStringNotContainsString(
            'onclick="closeModal(\'modalCreateSchedule\')" aria-label="Close"',
            $view
        );
        self::assertStringContainsString(
            'onclick="closeModal(\'modalCreateSchedule\')" class="btn btn-secondary">Cancel</button>',
            $view
        );
    }

    public function testDuplicateScheduleNameHasAFieldValidationMessage(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/ScheduleController.php');

        self::assertIsString($controller);
        self::assertStringContainsString('$repo->scheduleNameExists($data[\'schedule_name\'])', $controller);
        self::assertStringContainsString(
            "A work schedule with this name already exists.",
            $controller
        );
    }
}
