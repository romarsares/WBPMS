<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeder A3a: Roles and Request Types.
 *
 * Seeds:
 *   role          — BusinessOwner, HRHead, Employee
 *   request_type  — Leave, Overtime, CashAdvance
 *
 * These are reference/lookup rows required by all other seeders.
 * Run order: this seeder must run before DemoUserSeeder.
 */
class RolesAndRequestTypesSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [];
    }

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // -------------------------------------------------------------------
        // role
        // -------------------------------------------------------------------
        $roleTable = $this->table('role');

        // Use INSERT IGNORE so the seeder is idempotent on re-run
        $this->execute("
            INSERT IGNORE INTO `role` (role_name, created_at, updated_at)
            VALUES
                ('BusinessOwner', '{$now}', '{$now}'),
                ('HRHead',        '{$now}', '{$now}'),
                ('Employee',      '{$now}', '{$now}')
        ");

        // -------------------------------------------------------------------
        // request_type
        // -------------------------------------------------------------------
        $this->execute("
            INSERT IGNORE INTO `request_type` (type_name, status, created_at, updated_at)
            VALUES
                ('Leave',        'Active', '{$now}', '{$now}'),
                ('Overtime',     'Active', '{$now}', '{$now}'),
                ('CashAdvance',  'Active', '{$now}', '{$now}')
        ");

        // INSERT IGNORE above relies on the UQ index on (type_name) and (role_name).
        // No data is output to the console — check counts via SELECT after seeding.
    }
}
