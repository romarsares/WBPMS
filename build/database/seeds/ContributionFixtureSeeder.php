<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeder A3d: ADR-0001 Approved Contribution and Payroll Policy Fixture.
 *
 * ⚠  DEMO ONLY — NOT PRODUCTION STATUTORY RATES ⚠
 *
 * This fixture implements exactly the Final Defense Reviewer example
 * approved in ADR-0001 §Contributions. It is the ONLY authorised demo
 * contribution fixture for the MVP. It must NOT be used for production
 * payroll without HR-confirmed, formally-approved statutory rate tables.
 *
 * Approved fixture values (ADR-0001):
 *   daily_rate        ₱460.00
 *   EEMR              (460 × 313) ÷ 12 = ₱11,998.33
 *   SSS employee      ₱540.00
 *   SSS employer      ₱1,140.00
 *   PhilHealth rate   4% split equally (2% employee / 2% employer)
 *   PhilHealth emp    ₱239.97  (half-up rounding of ₱11,998.33 × 0.02)
 *   Pag-IBIG          ₱200.00 employee / ₱200.00 employer (fixed)
 *   Annual tax thresh ₱250,000.00 (demo employees are below threshold)
 *   Late rate         ₱1.00 per minute
 *
 * Seeds:
 *   payroll_policy_version   — DEMO-PAYROLL-V1 (Approved, demo_only=TRUE)
 *   contribution_policy_version — DEMO-CONTRIB-V1 (Approved, demo_only=TRUE)
 *   sss_bracket              — single bracket covering EEMR of ₱11,998.33
 *   philhealth_rate          — 4% rate with 2% employee share
 *   pagibig_rate             — ₱200 fixed employee + ₱200 employer
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
        $now         = date('Y-m-d H:i:s');
        $effectiveFrom = '2024-01-01';

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

        // -------------------------------------------------------------------
        // contribution_policy_version — DEMO-CONTRIB-V1
        // -------------------------------------------------------------------
        $cpvExists = $this->fetchRow(
            "SELECT contribution_policy_id FROM contribution_policy_version WHERE policy_code = 'DEMO-CONTRIB' AND version = 'V1'"
        );

        if ($cpvExists === false) {
            $this->execute("
                INSERT INTO contribution_policy_version
                    (policy_code, version, effective_from, effective_to,
                     demo_only, status, approved_by, approved_at, created_at, updated_at)
                VALUES
                    ('DEMO-CONTRIB', 'V1', '{$effectiveFrom}', NULL,
                     TRUE, 'Approved', {$ownerUserId}, '{$now}', '{$now}', '{$now}')
            ");
        }

        $cpvId = $this->fetchContribPolicyId('DEMO-CONTRIB', 'V1');

        // -------------------------------------------------------------------
        // sss_bracket — single bracket for EEMR ~₱11,998 (₱11,000–₱12,499.99)
        // The Final Defense Reviewer example uses a fixed SSS amount, not a
        // percentage; the bracket represents the range where the demo EEMR falls.
        // salary_to NULL = no upper bound (catches any value >= salary_from).
        // -------------------------------------------------------------------
        $sssExists = $this->fetchRow(
            "SELECT bracket_id FROM sss_bracket
             WHERE contribution_policy_id = {$cpvId} AND salary_from = 11000.00"
        );

        if ($sssExists === false) {
            $this->execute("
                INSERT INTO sss_bracket
                    (contribution_policy_id, salary_from, salary_to,
                     employee_share, employer_share,
                     effective_from, effective_to, created_at, updated_at)
                VALUES
                    ({$cpvId}, 11000.00, NULL,
                     540.00, 1140.00,
                     '{$effectiveFrom}', NULL, '{$now}', '{$now}')
            ");
        }

        // -------------------------------------------------------------------
        // philhealth_rate — 4% total; 2% employee share
        // basis_ceiling NULL = rate applies to full EEMR (no cap in demo fixture)
        // -------------------------------------------------------------------
        $phExists = $this->fetchRow(
            "SELECT rate_id FROM philhealth_rate WHERE contribution_policy_id = {$cpvId}"
        );

        if ($phExists === false) {
            $this->execute("
                INSERT INTO philhealth_rate
                    (contribution_policy_id, rate_decimal, basis_floor, basis_ceiling,
                     employee_share_decimal, created_at, updated_at)
                VALUES
                    ({$cpvId}, 0.040000, NULL, NULL,
                     0.020000, '{$now}', '{$now}')
            ");
        }

        // -------------------------------------------------------------------
        // pagibig_rate — ₱200 fixed employee + ₱200 employer per ADR-0001
        // -------------------------------------------------------------------
        $piExists = $this->fetchRow(
            "SELECT rate_id FROM pagibig_rate WHERE contribution_policy_id = {$cpvId}"
        );

        if ($piExists === false) {
            $this->execute("
                INSERT INTO pagibig_rate
                    (contribution_policy_id, rate_decimal, basis_ceiling,
                     employee_fixed_amount, employer_fixed_amount, created_at, updated_at)
                VALUES
                    ({$cpvId}, NULL, NULL,
                     200.00, 200.00, '{$now}', '{$now}')
            ");
        }
    }

    private function fetchUserId(string $username): int
    {
        $row = $this->fetchRow("SELECT user_id FROM `users` WHERE username = '{$username}'");
        if ($row === false || !isset($row['user_id'])) {
            throw new \RuntimeException("User '{$username}' not found. Run DemoUserSeeder first.");
        }

        return (int) $row['user_id'];
    }

    private function fetchContribPolicyId(string $code, string $version): int
    {
        $row = $this->fetchRow(
            "SELECT contribution_policy_id FROM contribution_policy_version
             WHERE policy_code = '{$code}' AND version = '{$version}'"
        );
        if ($row === false || !isset($row['contribution_policy_id'])) {
            throw new \RuntimeException("contribution_policy_version '{$code}/{$version}' not found.");
        }

        return (int) $row['contribution_policy_id'];
    }
}
