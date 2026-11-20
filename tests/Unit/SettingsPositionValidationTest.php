<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Wbpms\Http\Controllers\SettingsController;

final class SettingsPositionValidationTest extends TestCase
{
    public function testPositionRateCannotExceedEmployeeSalaryCap(): void
    {
        $_POST = [
            'position_title' => 'Cashier',
            'department' => '',
            'sort_order' => '1',
            'default_daily_rate' => '800.01',
            'contractual_allowed' => '1',
        ];

        try {
            $controller = new SettingsController();
            $method = new ReflectionMethod($controller, 'extractPosition');
            /** @var array{errors:array<string,string>} $result */
            $result = $method->invoke($controller);

            self::assertSame(
                'Default daily rate cannot exceed ₱800.00.',
                $result['errors']['default_daily_rate']
            );
        } finally {
            $_POST = [];
        }
    }
}
