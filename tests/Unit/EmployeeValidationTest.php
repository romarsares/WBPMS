<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Wbpms\Http\Controllers\EmployeeController;

final class EmployeeValidationTest extends TestCase
{
    public function testEmployeeAddRejectsDailyRateAboveMaximum(): void
    {
        $errors = $this->validateEmployee([
            'branch_id' => '1',
            'schedule_id' => '1',
            'device_id' => '1',
            'enrollment_code' => '00123',
            'daily_rate' => '800.01',
        ], false);

        self::assertSame('Daily rate cannot exceed ₱800.00.', $errors['daily_rate']);
    }

    public function testEmployeeEditDoesNotChangeSalaryThroughProfileForm(): void
    {
        $errors = $this->validateEmployee(['daily_rate' => '800.01']);

        self::assertArrayNotHasKey('daily_rate', $errors);
    }

    public function testEmployeeEditRejectsApplicantUnderEighteenOnHireDate(): void
    {
        $errors = $this->validateEmployee([
            'birthdate' => '2008-07-02',
            'effective_from' => '2026-07-01',
        ]);

        self::assertSame('Employee must be at least 18 years old on the hire date.', $errors['birthdate']);
    }

    public function testEmployeeEditRejectsInvalidHireTime(): void
    {
        $errors = $this->validateEmployee(['hire_time' => '25:00']);

        self::assertSame('Hire time must use the HH:MM format.', $errors['hire_time']);
    }

    public function testEmployeeEditAcceptsValidHireTime(): void
    {
        $errors = $this->validateEmployee(['hire_time' => '14:30']);

        self::assertArrayNotHasKey('hire_time', $errors);
    }

    public function testRehireRejectsDailyRateAboveMaximum(): void
    {
        $controller = new EmployeeController();
        $method = new ReflectionMethod($controller, 'validateRehire');
        $errors = $method->invoke($controller, [
            'rehire_date' => '2026-07-01',
            'branch_id' => 1,
            'schedule_id' => 1,
            'device_id' => 1,
            'enrollment_code' => '00123',
            'daily_rate' => 800.01,
            'position' => 'Staff',
            'employee_type' => 'Regular',
            'notes' => '',
        ]);

        self::assertSame('Daily rate cannot exceed ₱800.00.', $errors['daily_rate']);
    }

    /** @param array<string,string> $changes @return array<string,string> */
    private function validateEmployee(array $changes, bool $isEdit = true): array
    {
        $controller = new EmployeeController();
        $method = new ReflectionMethod($controller, 'validate');
        $data = array_merge([
            'employee_number' => 'EMP-001',
            'employee_type' => 'Regular',
            'first_name' => 'Lovely',
            'middle_name' => '',
            'last_name' => 'Dela Cruz',
            'email' => 'lovely@example.test',
            'contact_number' => '09171234567',
            'birthdate' => '1990-01-01',
            'position' => 'Staff',
            'hire_date' => '2020-01-01',
            'hire_time' => '',
            'effective_from' => '2026-07-01',
            'branch_id' => '',
            'schedule_id' => '',
            'device_id' => '',
            'enrollment_code' => '',
            'daily_rate' => '800.00',
            'philhealth_number' => '',
            'pagibig_number' => '',
            'tin_number' => '',
            'sss_number' => '',
            'contract_review_date' => '',
            'status' => 'Active',
        ], $changes);

        return $method->invoke($controller, $data, $isEdit, null, $isEdit ? 1 : null);
    }
}
