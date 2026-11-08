<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeder A3d: Payroll Policy Fixture and Business Owner setup.
 *
 * Seeds:
 *   payroll_policy_version — DEMO-PAYROLL-V1 (Approved, demo_only=TRUE)
 *
 * Contribution policy (CONTRIB-2026) is seeded by Contribution2026Seeder.
 *
 * Run order: DemoUserSeeder must run first (approved_by references users).
 */
class ContributionFixtureSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['DemoUserSeeder'];
    }

    public function run(): void
    {
        $now           = date('Y-m-d H:i:s');
        $effectiveFrom = '2026-01-01';

        // The Business Owner user approves these policy records
        $ownerUserId = $this->fetchUserId('owner');

        // -------------------------------------------------------------------
        // payroll_policy_version — DEMO-PAYROLL-V1
        // -------------------------------------------------------------------
        $ppvExists = $this->fetchRow(
            "SELECT policy_id FROM payroll_policy_version WHERE policy_code = 'DEMO-PAYROLL' AND version = 'V1'"
        );

        if ($ppvExists === false) {
            $this->execute("
                INSERT INTO payroll_policy_version
                    (policy_code, version, effective_from, effective_to,
                     eemr_days_per_year, annual_tax_threshold, late_rate_per_minute,
                     rounding_mode, demo_only, status, approved_by, approved_at, created_at, updated_at)
                VALUES
                    ('DEMO-PAYROLL', 'V1', '{$effectiveFrom}', NULL,
                     313, 250000.00, 1.00,
                     'HalfUp', TRUE, 'Approved', {$ownerUserId}, '{$now}', '{$now}', '{$now}')
            ");
        }

        // Contribution policy CONTRIB-2026 is seeded by Contribution2026Seeder.
    }

    private function fetchUserId(string $username): int
    {
        $row = $this->fetchRow("SELECT user_id FROM `users` WHERE username = '{$username}'");
        if ($row === false || !isset($row['user_id'])) {
            throw new \RuntimeException("User '{$username}' not found. Run DemoUserSeeder first.");
        }

        return (int) $row['user_id'];
    }
}
