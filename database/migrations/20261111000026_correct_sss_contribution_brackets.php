<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Corrects the shifted SSS bracket ranges in the 2026 statutory policy.
 *
 * The original bracket generator began each range at the Monthly Salary Credit
 * (MSC). SSS Circular 2024-006 centres each ₱500 range on its MSC instead:
 * ₱18,250–₱18,749.99 maps to the ₱18,500 MSC and ₱925 employee share.
 * A replacement policy keeps already-approved payroll contribution records
 * linked to the original policy for audit history.
 */
final class CorrectSssContributionBrackets extends AbstractMigration
{
    public function up(): void
    {
        $legacy = $this->fetchRow(
            "SELECT contribution_policy_id, effective_from, demo_only, approved_by, approved_at
               FROM contribution_policy_version
              WHERE policy_code IN ('PH-STATUTORY-2026', 'CONTRIB-2026')
                AND version = 'V1'
              ORDER BY contribution_policy_id DESC
              LIMIT 1"
        );
        if ($legacy === false) {
            return;
        }

        $replacement = $this->fetchRow(
            "SELECT contribution_policy_id
               FROM contribution_policy_version
              WHERE policy_code = 'PH-STATUTORY-2026' AND version = 'V2'
              LIMIT 1"
        );

        if ($replacement === false) {
            $legacyId = (int) $legacy['contribution_policy_id'];
            $effectiveFrom = addslashes((string) $legacy['effective_from']);
            $demoOnly = (int) $legacy['demo_only'];
            $approvedBy = $legacy['approved_by'] === null ? 'NULL' : (string) (int) $legacy['approved_by'];
            $approvedAt = $legacy['approved_at'] === null
                ? 'NULL'
                : "'" . addslashes((string) $legacy['approved_at']) . "'";

            $this->execute(
                "INSERT INTO contribution_policy_version
                    (policy_code, version, effective_from, effective_to, demo_only, status,
                     approved_by, approved_at, created_at, updated_at)
                 VALUES
                    ('PH-STATUTORY-2026', 'V2', '{$effectiveFrom}', NULL, {$demoOnly}, 'Approved',
                     {$approvedBy}, {$approvedAt}, NOW(), NOW())"
            );
            $replacement = $this->fetchRow(
                "SELECT contribution_policy_id
                   FROM contribution_policy_version
                  WHERE policy_code = 'PH-STATUTORY-2026' AND version = 'V2'
                  LIMIT 1"
            );
            if ($replacement === false) {
                throw new RuntimeException('Unable to create corrected SSS contribution policy.');
            }

            $replacementId = (int) $replacement['contribution_policy_id'];
            $this->execute(
                "INSERT INTO philhealth_rate
                    (contribution_policy_id, rate_decimal, basis_floor, basis_ceiling,
                     employee_share_decimal, created_at, updated_at)
                 SELECT {$replacementId}, rate_decimal, basis_floor, basis_ceiling,
                        employee_share_decimal, NOW(), NOW()
                   FROM philhealth_rate
                  WHERE contribution_policy_id = {$legacyId}"
            );
            $this->execute(
                "INSERT INTO pagibig_rate
                    (contribution_policy_id, rate_decimal, basis_ceiling,
                     employee_fixed_amount, employer_fixed_amount, created_at, updated_at)
                 SELECT {$replacementId}, rate_decimal, basis_ceiling,
                        employee_fixed_amount, employer_fixed_amount, NOW(), NOW()
                   FROM pagibig_rate
                  WHERE contribution_policy_id = {$legacyId}"
            );
        }

        $replacementId = (int) $replacement['contribution_policy_id'];
        $bracketRow = $this->fetchRow(
            "SELECT COUNT(*) AS bracket_count FROM sss_bracket WHERE contribution_policy_id = {$replacementId}"
        );
        $bracketCount = (int) ($bracketRow['bracket_count'] ?? 0);

        if ($bracketCount === 0) {
            $rows = [sprintf('(%d, 0.00, 5249.99, 250.00, 500.00, \'2025-01-01\', NULL, NOW(), NOW())', $replacementId)];
            for ($msc = 5500; $msc <= 35000; $msc += 500) {
                $from = $msc - 250;
                $to = $msc === 35000 ? 'NULL' : number_format($msc + 249.99, 2, '.', '');
                $rows[] = sprintf(
                    '(%d, %.2F, %s, %.2F, %.2F, \'2025-01-01\', NULL, NOW(), NOW())',
                    $replacementId,
                    $from,
                    $to,
                    $msc * 0.05,
                    $msc * 0.10
                );
            }
            $this->execute(
                'INSERT INTO sss_bracket
                    (contribution_policy_id, salary_from, salary_to, employee_share, employer_share,
                     effective_from, effective_to, created_at, updated_at) VALUES '
                . implode(', ', $rows)
            );
        }

        $legacyId = (int) $legacy['contribution_policy_id'];
        $this->execute(
            "UPDATE contribution_policy_version
                SET status = 'Retired', updated_at = NOW()
              WHERE contribution_policy_id = {$legacyId}
                AND contribution_policy_id <> {$replacementId}"
        );
    }

    public function down(): void
    {
        $replacement = $this->fetchRow(
            "SELECT contribution_policy_id FROM contribution_policy_version
              WHERE policy_code = 'PH-STATUTORY-2026' AND version = 'V2' LIMIT 1"
        );
        if ($replacement === false) {
            return;
        }

        $replacementId = (int) $replacement['contribution_policy_id'];
        $usageRow = $this->fetchRow(
            "SELECT COUNT(*) AS record_count FROM contribution_record
              WHERE contribution_policy_id = {$replacementId}"
        );
        if ((int) ($usageRow['record_count'] ?? 0) > 0) {
            throw new RuntimeException('Cannot revert an SSS policy correction that has been used by payroll.');
        }

        $this->execute("DELETE FROM sss_bracket WHERE contribution_policy_id = {$replacementId}");
        $this->execute("DELETE FROM philhealth_rate WHERE contribution_policy_id = {$replacementId}");
        $this->execute("DELETE FROM pagibig_rate WHERE contribution_policy_id = {$replacementId}");
        $this->execute("DELETE FROM contribution_policy_version WHERE contribution_policy_id = {$replacementId}");
        $this->execute(
            "UPDATE contribution_policy_version
                SET status = 'Approved', updated_at = NOW()
              WHERE policy_code IN ('PH-STATUTORY-2026', 'CONTRIB-2026')
                AND version = 'V1'"
        );
    }
}