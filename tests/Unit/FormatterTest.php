<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbpms\Http\View\Formatter;

final class FormatterTest extends TestCase
{
    public function testTimeUsesTwelveHourDisplayFormat(): void
    {
        self::assertSame('7:00 AM', Formatter::time('07:00:00'));
        self::assertSame('1:30 PM', Formatter::time('13:30:00'));
    }

    public function testInputTimeRemainsCompatibleWithTwentyFourHourFormInputs(): void
    {
        self::assertSame('07:00', Formatter::inputTime('07:00:00'));
        self::assertSame('13:30', Formatter::inputTime('13:30:00'));
    }
}
