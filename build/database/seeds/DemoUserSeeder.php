<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeder A3c: Demo Users.
 *
 * Seeds three test accounts — one per role.
 * Passwords are hashed with password_hash(PASSWORD_DEFAULT) per ADR-0001.
 *
 * Demo credentials (NEVER use in production):
 *   owner@demo.test   / owner-demo-pass
 *   hrhead@demo.test  / hrhead-demo-pass
 *   employee@demo.test / employee-demo-pass
 *
 * employee_id is intentionally NULL for BusinessOwner (non-payroll user).
 * The HRHead and BusinessOwner user rows have no employee link at seed time.
 * The demo Employee user will be linked to the employee row by DemoEmployeeSeeder.
 *
 * Run order: RolesAndRequestTypesSeeder must run first.
 */
class DemoUserSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['RolesAndRequestTypesSeeder'];
    }

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $ownerRoleId    = $this->fetchRoleId('BusinessOwner');
        $hrheadRoleId   = $this->fetchRoleId('HRHead');
        $employeeRoleId = $this->fetchRoleId('Employee');

        // Hash passwords with PASSWORD_DEFAULT (bcrypt at cost 10+)
        // These are synthetic demo values; change before any real use.
        $ownerHash    = password_hash('owner-demo-pass',    PASSWORD_DEFAULT);
        $hrheadHash   = password_hash('hrhead-demo-pass',   PASSWORD_DEFAULT);
        $employeeHash = password_hash('employee-demo-pass', PASSWORD_DEFAULT);

        // Insert only if the username does not already exist (idempotent)
        $this->insertIfAbsent('owner',    'owner@demo.test',    $ownerRoleId,    null, $ownerHash,    $now);
        $this->insertIfAbsent('hrhead',   'hrhead@demo.test',   $hrheadRoleId,   null, $hrheadHash,   $now);
        $this->insertIfAbsent('employee', 'employee@demo.test', $employeeRoleId, null, $employeeHash, $now);

        // Note: the employee user's employee_id will be set by DemoEmployeeSeeder
        // after the employee row is created, to avoid a circular seed dependency.
    }

    private function fetchRoleId(string $roleName): int
    {
        $row = $this->fetchRow("SELECT role_id FROM `role` WHERE role_name = '{$roleName}'");
        if ($row === false || !isset($row['role_id'])) {
            throw new \RuntimeException("Role '{$roleName}' not found. Run RolesAndRequestTypesSeeder first.");
        }

        return (int) $row['role_id'];
    }

    private function insertIfAbsent(
        string  $username,
        string  $email,
        int     $roleId,
        ?int    $employeeId,
        string  $passwordHash,
        string  $now
    ): void {
        $exists = $this->fetchRow(
            "SELECT user_id FROM `users` WHERE username = '" . $username . "'"
        );

        if ($exists !== false) {
            return; // Already seeded
        }

        $empVal = ($employeeId === null) ? 'NULL' : (string) $employeeId;

        $this->execute("
            INSERT INTO `users`
                (employee_id, role_id, username, account_email, password_hash, status, created_at, updated_at)
            VALUES
                ({$empVal}, {$roleId}, '{$username}', '{$email}', '{$passwordHash}', 'Active', '{$now}', '{$now}')
        ");
    }
}
