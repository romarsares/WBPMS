<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit\EmployeePortal;

use Wbpms\Application\EmployeePortal\EmployeeScope;
use DomainException;
use PHPUnit\Framework\TestCase;

final class EmployeeScopeTest extends TestCase
{
    public function testEmployeeCannotReadAnotherEmployeesRecord(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('FORBIDDEN_EMPLOYEE_RECORD');
        (new EmployeeScope())->assertOwnRecord(10, 11);
    }
}
