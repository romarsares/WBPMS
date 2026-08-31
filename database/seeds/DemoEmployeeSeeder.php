<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeder A3e: Demo Employee.
 *
 * Seeds a single demo employee with:
 *   - employee record (Banga Construction Supplies branch)
 *   - branch assignment (BANGA-CON, effective 2024-01-01)
 *   - work schedule (Mon–Fri 07:00–16:00, 60min break, 480 standard minutes, Sat rest)
 *   - biometric enrollment (DEVICE-BANGA, code "00001")
 *   - salary (daily_rate = ₱460.00 per ADR-0001 approved demo fixture)
 *   - links users.employee → this employee row
 *
 * All values are synthetic demo data. No real employee information is used.
 *
 * Run order: DemoUserSeeder, DemoBranchesSeeder must run first.
 */
class DemoEmployeeSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['DemoUserSeeder', 'DemoBranchesSeeder', 'RolesAndRequestTypesSeeder'];
    }

    public function run(): void
    {
        $now           = date('Y-m-d H:i:s');
        $effectiveFrom = '2024-01-01';

        $hrUserId  = $this->fetchUserId('hrhead');
        $branchId  = $this->fetchBranchId('BANGA-CON');
        $deviceId  = $this->fetchDeviceId('DEVICE-BANGA');
        $empUserId = $this->fetchUserId('employee');

        // -------------------------------------------------------------------
        // employee
        // -------------------------------------------------------------------
        $empExists = $this->fetchRow(
            "SELECT employee_id FROM employee WHERE employee_number = 'EMP-DEMO-001'"
        );

        if ($empExists === false) {
            $this->execute("
                INSERT INTO employee
                    (employee_number, employee_type, first_name, middle_initial, last_name,
                     email, hire_date, position, status, created_at, updated_at)
                VALUES
                    ('EMP-DEMO-001', 'Regular', 'Juan', 'D', 'Dela Cruz',
                     'juandelacruz@demo.test', '2024-01-01', 'Construction Worker', 'Active', '{$now}', '{$now}')
            ");
        }

        $employeeId = $this->fetchEmployeeId('EMP-DEMO-001');

        // -------------------------------------------------------------------
        // Link the demo Employee user to this employee record
        // -------------------------------------------------------------------
        $this->execute("
            UPDATE `users`
            SET employee_id = {$employeeId}, updated_at = '{$now}'
            WHERE username = 'employee' AND employee_id IS NULL
        ");

        // -------------------------------------------------------------------
        // employee_branch_assignment
        // -------------------------------------------------------------------
        $assignExists = $this->fetchRow(
            "SELECT branch_assignment_id FROM employee_branch_assignment
             WHERE employee_id = {$employeeId} AND effective_from = '{$effectiveFrom}'"
        );

        if ($assignExists === false) {
            $this->execute("
                INSERT INTO employee_branch_assignment
                    (employee_id, branch_id, effective_from, effective_to,
                     transfer_reason, transferred_by, created_at)
                VALUES
                    ({$employeeId}, {$branchId}, '{$effectiveFrom}', NULL,
                     'Initial assignment', {$hrUserId}, '{$now}')
            ");
        }

        // -------------------------------------------------------------------
        // work_schedule  — Mon-Fri, 07:00-16:00, 60 min break, 480 standard mins
        // Default documented shift per ADR-0001: 07:00–16:00
        // -------------------------------------------------------------------
        $schedExists = $this->fetchRow(
            "SELECT schedule_id FROM work_schedule
             WHERE employee_id = {$employeeId} AND effective_from = '{$effectiveFrom}'"
        );

        if ($schedExists === false) {
            $workingDaysJson = json_encode(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
            $restDaysJson    = json_encode(['Saturday', 'Sunday']);

            $this->execute("
                INSERT INTO work_schedule
                    (employee_id, working_days, rest_days, break_minutes,
                     work_start_time, work_end_time, standard_minutes,
                     effective_from, effective_to, status, created_at, updated_at)
                VALUES
                    ({$employeeId},
                     '{$workingDaysJson}',
                     '{$restDaysJson}',
                     60,
                     '07:00:00', '16:00:00', 480,
                     '{$effectiveFrom}', NULL, 'Active', '{$now}', '{$now}')
            ");
        }

        // -------------------------------------------------------------------
        // employee_biometric_enrollment
        // Enrollment code '00001' — leading zeroes preserved (ADR-0003)
        // -------------------------------------------------------------------
        $enrollExists = $this->fetchRow(
            "SELECT enrollment_id FROM employee_biometric_enrollment
             WHERE device_id = {$deviceId} AND device_employee_code = '00001'
             AND effective_from = '{$effectiveFrom}'"
        );

        if ($enrollExists === false) {
            $this->execute("
                INSERT INTO employee_biometric_enrollment
                    (employee_id, device_id, device_employee_code,
                     effective_from, effective_to, status, created_at, updated_at)
                VALUES
                    ({$employeeId}, {$deviceId}, '00001',
                     '{$effectiveFrom}', NULL, 'Active', '{$now}', '{$now}')
            ");
        }

        // -------------------------------------------------------------------
        // salary — ₱460.00 daily rate per ADR-0001 demo fixture
        // -------------------------------------------------------------------
        $salaryExists = $this->fetchRow(
            "SELECT salary_id FROM salary
             WHERE employee_id = {$employeeId} AND effective_from = '{$effectiveFrom}'"
        );

        if ($salaryExists === false) {
            $this->execute("
                INSERT INTO salary
                    (employee_id, daily_rate, effective_from, effective_to,
                     status, created_by, created_at, updated_at)
                VALUES
                    ({$employeeId}, 460.00, '{$effectiveFrom}', NULL,
                     'Active', {$hrUserId}, '{$now}', '{$now}')
            ");
        }
    }

    private function fetchUserId(string $username): int
    {
        $row = $this->fetchRow("SELECT user_id FROM `users` WHERE username = '{$username}'");
        if ($row === false || !isset($row['user_id'])) {
            throw new \RuntimeException("User '{$username}' not found.");
        }

        return (int) $row['user_id'];
    }

    private function fetchBranchId(string $branchCode): int
    {
        $row = $this->fetchRow("SELECT branch_id FROM branch WHERE branch_code = '{$branchCode}'");
        if ($row === false || !isset($row['branch_id'])) {
            throw new \RuntimeException("Branch '{$branchCode}' not found.");
        }

        return (int) $row['branch_id'];
    }

    private function fetchDeviceId(string $deviceCode): int
    {
        $row = $this->fetchRow("SELECT device_id FROM biometric_device WHERE device_code = '{$deviceCode}'");
        if ($row === false || !isset($row['device_id'])) {
            throw new \RuntimeException("Device '{$deviceCode}' not found.");
        }

        return (int) $row['device_id'];
    }

    private function fetchEmployeeId(string $employeeNumber): int
    {
        $row = $this->fetchRow("SELECT employee_id FROM employee WHERE employee_number = '{$employeeNumber}'");
        if ($row === false || !isset($row['employee_id'])) {
            throw new \RuntimeException("Employee '{$employeeNumber}' not found.");
        }

        return (int) $row['employee_id'];
    }
}
