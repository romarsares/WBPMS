<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fix PH-STATUTORY-2026 contribution rates.
 *
 * The production database was seeded with incorrect PhilHealth and SSS data:
 *
 *   PhilHealth: rate was 4% (0.04) with empty floor/ceiling and 2% EE share.
 *               Correct per RA 11223 (UHC Act, effective 2024):
 *               5% total (0.05), 2.5% EE share (0.025),
 *               floor ₱10,000 / ceiling ₱100,000.
 *
 *   SSS: only 1 bracket existed. Correct per RA 11199 final tranche (2025):
 *        15% total (10% ER / 5% EE), MSC ₱5,000–₱35,000 in ₱500 steps
 *        = 61 brackets (1 floor + 60 stepped).
 *
 * This migration replaces both with correct 2026 statutory values.
 */
final class FixPhStatutory2026ContributionRates extends AbstractMigration
{
    public function up(): void
    {
        // --- Resolve policy ID -------------------------------------------
        $row = $this->fetchRow(
            "SELECT contribution_policy_id FROM contribution_policy_version
              WHERE policy_code = 'PH-STATUTORY-2026' AND version = 'V1' LIMIT 1"
        );
        if ($row === false) {
            // Nothing to fix — policy doesn't exist on this environment.
            return;
        }
        $pid = (int) $row['contribution_policy_id'];
        $now = date('Y-m-d H:i:s');
        $eff = '2026-01-01';

        // --- Fix PhilHealth rate -----------------------------------------
        // RA 11223: 5% total, 2.5% EE share, floor ₱10,000 / ceiling ₱100,000
        $this->execute(
            "UPDATE philhealth_rate
                SET rate_decimal          = 0.050000,
                    basis_floor           = 10000.00,
                    basis_ceiling         = 100000.00,
                    employee_share_decimal = 0.025000,
                    updated_at            = '{$now}'
              WHERE contribution_policy_id = {$pid}"
        );

        // --- Rebuild SSS bracket table -----------------------------------
        // RA 11199 final tranche (effective 2025, carried through 2026):
        //   15% total: 5% EE / 10% ER, MSC ₱5,000–₱35,000 in ₱500 steps.
        $this->execute(
            "DELETE FROM sss_bracket WHERE contribution_policy_id = {$pid}"
        );

        $sssEE   = 0.05;
        $sssER   = 0.10;
        $floor   = 5000;
        $ceiling = 35000;
        $step    = 500;

        $rows = [];

        // Floor bracket: covers 0.00–5,499.99 → MSC ₱5,000
        $rows[] = sprintf(
            "(%d, 0.00, 5499.99, %.2f, %.2f, '%s', NULL, '%s', '%s')",
            $pid,
            $floor * $sssEE,   // 250.00
            $floor * $sssER,   // 500.00
            $eff, $now, $now
        );

        for ($msc = $floor + $step; $msc <= $ceiling; $msc += $step) {
            $salaryTo = ($msc === $ceiling)
                ? 'NULL'
                : sprintf("'%.2f'", $msc + $step - 0.01);

            $rows[] = sprintf(
                "(%d, %.2f, %s, %.2f, %.2f, '%s', NULL, '%s', '%s')",
                $pid,
                (float) $msc,
                $salaryTo,
                $msc * $sssEE,
                $msc * $sssER,
                $eff, $now, $now
            );
        }

        $this->execute(
            "INSERT INTO sss_bracket
                (contribution_policy_id, salary_from, salary_to,
                 employee_share, employer_share, effective_from, effective_to,
                 created_at, updated_at)
             VALUES " . implode(', ', $rows)
        );
    }

    public function down(): void
    {
        // Restore old incorrect values (for rollback traceability only).
        $row = $this->fetchRow(
            "SELECT contribution_policy_id FROM contribution_policy_version
              WHERE policy_code = 'PH-STATUTORY-2026' AND version = 'V1' LIMIT 1"
        );
        if ($row === false) {
            return;
        }
        $pid = (int) $row['contribution_policy_id'];
        $now = date('Y-m-d H:i:s');

        $this->execute(
            "UPDATE philhealth_rate
                SET rate_decimal           = 0.040000,
                    basis_floor            = NULL,
                    basis_ceiling          = NULL,
                    employee_share_decimal = 0.020000,
                    updated_at             = '{$now}'
              WHERE contribution_policy_id = {$pid}"
        );
    }
}
