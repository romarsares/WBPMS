<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeder: 2026 Government Contribution Policy (statutory, non-demo).
 *
 * Creates an Approved, production-grade contribution_policy_version
 * (CONTRIB-2026 / V1, demo_only = FALSE, effective 2026-01-01) with:
 *
 *   SSS        — full MSC bracket table, 5% employee / 10% employer,
 *                MSC ₱5,000–₱35,000 in ₱500 steps (61 brackets).
 *   PhilHealth — 5.0% total, 2.5% employee share,
 *                basis floor ₱10,000 / ceiling ₱100,000.
 *   Pag-IBIG   — 2% rate, MFS cap ₱10,000 (max ₱200 EE / ₱200 ER),
 *                percentage mode (fixed amounts NULL).
 *
 * Rate sources (2026 contribution schedule):
 *   SSS:        15% total (10% employer / 5% employee); MSC ₱5,000–₱35,000;
 *                RA 11199 final tranche effective Jan 1 2025, carried through 2026.
 *   PhilHealth: 5.0% premium (UHC Law); maintained at 5% for 2026.
 *   Pag-IBIG:   2% EE / 2% ER, MFS cap ₱10,000 (HDMF Circular No. 460, Feb 2024);
 *                continues into 2026.
 *
 * Resolution: PayrollService::resolveContribPolicy() selects the latest Approved
 * policy by effective_from DESC. CONTRIB-2026 (2026-01-01) is the active policy.
 *
 * Dependencies: ContributionFixtureSeeder (creates the Business Owner approver).
 */
class Contribution2026Seeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['ContributionFixtureSeeder'];
    }

    public function run(): void
    {
        $now              = date('Y-m-d H:i:s');
        $effectiveFrom    = '2026-01-01';
        $ownerUserId      = $this->fetchUserId('owner');

        // SSS parameters
        $sssRateEmployee  = 0.05;   // 5% of MSC
        $sssRateEmployer  = 0.10;   // 10% of MSC
        $mscFloor         = 5000;   // lowest MSC (also the floor for sub-₱5,000 earners)
        $mscCeiling       = 35000; // top MSC (per 2026 schedule: ₱5,000–₱35,000)
        $mscStep          = 500;

        // -------------------------------------------------------------------
        // contribution_policy_version — CONTRIB-2026 / V1
        // -------------------------------------------------------------------
        $exists = $this->fetchRow(
            "SELECT contribution_policy_id FROM contribution_policy_version
              WHERE policy_code = 'CONTRIB-2026' AND version = 'V1'"
        );

        if ($exists === false) {
            $this->execute("
                INSERT INTO contribution_policy_version
                    (policy_code, version, effective_from, effective_to,
                     demo_only, status, approved_by, approved_at, created_at, updated_at)
                VALUES
                    ('CONTRIB-2026', 'V1', '{$effectiveFrom}', NULL,
                     FALSE, 'Approved', {$ownerUserId}, '{$now}', '{$now}', '{$now}')
            ");
        }

        $policyId = $this->fetchContribPolicyId('CONTRIB-2026', 'V1');

        // -------------------------------------------------------------------
        // sss_bracket — full MSC bracket table.
        //   Floor bracket (0.00–5,499.99) maps sub-₱5,000 earnings to MSC ₱5,000.
        //   Top bracket (₱35,000+) is open-ended (salary_to NULL) = ceiling.
        // -------------------------------------------------------------------
        $countRow     = $this->fetchRow(
            "SELECT COUNT(*) AS n FROM sss_bracket WHERE contribution_policy_id = {$policyId}"
        );
        $bracketCount = ($countRow !== false && isset($countRow['n'])) ? (int) $countRow['n'] : 0;

        if ($bracketCount === 0) {
            $valueRows = [];

            // Floor bracket: MSC ₱5,000, covers 0.00–5,499.99
            $valueRows[] = sprintf(
                '(%d, 0.00, 5499.99, %s, %s, %s, NULL, %s, %s)',
                $policyId,
                $this->money($mscFloor * $sssRateEmployee),  // 250.00
                $this->money($mscFloor * $sssRateEmployer),  // 500.00
                $this->sqlDate($effectiveFrom),
                $this->sqlDate($now),
                $this->sqlDate($now)
            );

            for ($msc = $mscFloor + $mscStep; $msc <= $mscCeiling; $msc += $mscStep) {
                $salaryTo = ($msc === $mscCeiling)
                    ? 'NULL'
                    : $this->money($msc + $mscStep - 0.01); // e.g. 5,500 → 5,999.99

                $valueRows[] = sprintf(
                    '(%d, %s, %s, %s, %s, %s, NULL, %s, %s)',
                    $policyId,
                    $this->money((float) $msc),
                    $salaryTo,
                    $this->money($msc * $sssRateEmployee),
                    $this->money($msc * $sssRateEmployer),
                    $this->sqlDate($effectiveFrom),
                    $this->sqlDate($now),
                    $this->sqlDate($now)
                );
            }

            $this->execute(
                'INSERT INTO sss_bracket '
                . '(contribution_policy_id, salary_from, salary_to, '
                . 'employee_share, employer_share, effective_from, effective_to, created_at, updated_at) VALUES '
                . implode(', ', $valueRows)
            );
        }

        // -------------------------------------------------------------------
        // philhealth_rate — 5% total; 2.5% employee share; floor ₱10k / ceiling ₱100k
        // -------------------------------------------------------------------
        $phExists = $this->fetchRow(
            "SELECT rate_id FROM philhealth_rate WHERE contribution_policy_id = {$policyId}"
        );
        if ($phExists === false) {
            $this->execute("
                INSERT INTO philhealth_rate
                    (contribution_policy_id, rate_decimal, basis_floor, basis_ceiling,
                     employee_share_decimal, created_at, updated_at)
                VALUES
                    ({$policyId}, 0.050000, 10000.00, 100000.00,
                     0.025000, '{$now}', '{$now}')
            ");
        }

        // -------------------------------------------------------------------
        // pagibig_rate — 2% rate; MFS cap ₱10,000 (max ₱200 EE / ₱200 ER).
        // Percentage mode: rate_decimal + basis_ceiling set; fixed amounts NULL
        // (satisfies chk_pagibig_mode).
        // -------------------------------------------------------------------
        $piExists = $this->fetchRow(
            "SELECT rate_id FROM pagibig_rate WHERE contribution_policy_id = {$policyId}"
        );
        if ($piExists === false) {
            $this->execute("
                INSERT INTO pagibig_rate
                    (contribution_policy_id, rate_decimal, basis_ceiling,
                     employee_fixed_amount, employer_fixed_amount, created_at, updated_at)
                VALUES
                    ({$policyId}, 0.020000, 10000.00,
                     NULL, NULL, '{$now}', '{$now}')
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

    /** Format a numeric value as a 2-dp SQL decimal literal. */
    private function money(float $v): string
    {
        return number_format(round($v, 2), 2, '.', '');
    }

    /** Quote a date/datetime string as a SQL literal. */
    private function sqlDate(string $v): string
    {
        return "'" . str_replace("'", "''", $v) . "'";
    }
}
