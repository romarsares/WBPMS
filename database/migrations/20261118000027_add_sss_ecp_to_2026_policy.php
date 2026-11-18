<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds the employer-only Employees' Compensation Program (ECP) amount to the
 * 2026 statutory SSS policy without changing historical payroll snapshots.
 *
 * SSS Circular 2024-006 retains the 5% employee / 10% employer SS split and
 * requires a further employer-only ECP payment: ₱10 for MSC up to ₱14,500 and
 * ₱30 for MSC of ₱15,000 and above. The prior approved policy is retired only
 * after the replacement V3 policy, rate rows, and all 61 brackets are present.
 */
final class AddSssEcpTo2026Policy extends AbstractMigration
{
    public function up(): void
    {
        $adapter = $this->getAdapter();
        $adapter->beginTransaction();

        try {
            $this->applyCorrection();
            $adapter->commitTransaction();
        } catch (\Throwable $exception) {
            $adapter->rollbackTransaction();

            throw $exception;
        }
    }

    private function applyCorrection(): void
    {
        $current = $this->fetchRow(
            "SELECT contribution_policy_id, effective_from, effective_to, demo_only, approved_by, approved_at
               FROM contribution_policy_version
              WHERE policy_code = 'PH-STATUTORY-2026' AND status = 'Approved'
              ORDER BY contribution_policy_id DESC
              LIMIT 1"
        );

        if ($current === false) {
            // Fresh databases seed the corrected V1 policy after all migrations.
            return;
        }

        $replacement = $this->fetchRow(
            "SELECT contribution_policy_id, status
               FROM contribution_policy_version
              WHERE policy_code = 'PH-STATUTORY-2026' AND version = 'V3'
              LIMIT 1"
        );

        if ($replacement === false) {
            $currentId = (int) $current['contribution_policy_id'];
            $effectiveFrom = addslashes((string) $current['effective_from']);
            $effectiveTo = $current['effective_to'] === null
                ? 'NULL'
                : "'" . addslashes((string) $current['effective_to']) . "'";
            $demoOnly = (int) $current['demo_only'];
            $approvedBy = $current['approved_by'] === null ? 'NULL' : (string) (int) $current['approved_by'];
            $approvedAt = $current['approved_at'] === null
                ? 'NULL'
                : "'" . addslashes((string) $current['approved_at']) . "'";

            $this->execute(
                "INSERT INTO contribution_policy_version
                    (policy_code, version, effective_from, effective_to, demo_only, status,
                     approved_by, approved_at, created_at, updated_at)
                 VALUES
                    ('PH-STATUTORY-2026', 'V3', '{$effectiveFrom}', {$effectiveTo}, {$demoOnly}, 'Approved',
                     {$approvedBy}, {$approvedAt}, NOW(), NOW())"
            );

            $replacement = $this->fetchRow(
                "SELECT contribution_policy_id
                   FROM contribution_policy_version
                  WHERE policy_code = 'PH-STATUTORY-2026' AND version = 'V3'
                  LIMIT 1"
            );
            if ($replacement === false) {
                throw new RuntimeException('Unable to create the SSS ECP policy correction.');
            }

            $replacementId = (int) $replacement['contribution_policy_id'];
            $this->execute(
                "INSERT INTO philhealth_rate
                    (contribution_policy_id, rate_decimal, basis_floor, basis_ceiling,
                     employee_share_decimal, created_at, updated_at)
                 SELECT {$replacementId}, rate_decimal, basis_floor, basis_ceiling,
                        employee_share_decimal, NOW(), NOW()
                   FROM philhealth_rate
                  WHERE contribution_policy_id = {$currentId}"
            );
            $this->execute(
                "INSERT INTO pagibig_rate
                    (contribution_policy_id, rate_decimal, basis_ceiling,
                     employee_fixed_amount, employer_fixed_amount, created_at, updated_at)
                 SELECT {$replacementId}, rate_decimal, basis_ceiling,
                        employee_fixed_amount, employer_fixed_amount, NOW(), NOW()
                   FROM pagibig_rate
                  WHERE contribution_policy_id = {$currentId}"
            );
        } elseif ((string) $replacement['status'] !== 'Approved') {
            throw new RuntimeException('Existing PH-STATUTORY-2026 V3 policy is not approved; refusing an unsafe correction.');
        }

        $replacementId = (int) $replacement['contribution_policy_id'];
        $bracketRow = $this->fetchRow(
            "SELECT COUNT(*) AS bracket_count FROM sss_bracket WHERE contribution_policy_id = {$replacementId}"
        );
        $effectiveRow = $this->fetchRow(
            "SELECT effective_from FROM contribution_policy_version
              WHERE contribution_policy_id = {$replacementId}"
        );
        if ($effectiveRow === false || !isset($effectiveRow['effective_from'])) {
            throw new RuntimeException('Unable to resolve the SSS ECP policy effective date.');
        }
        $effectiveFrom = addslashes((string) $effectiveRow['effective_from']);

        if ((int) ($bracketRow['bracket_count'] ?? 0) === 0) {
            $rows = [sprintf('(%d, 0.00, 5249.99, 250.00, 510.00, \'%s\', NULL, NOW(), NOW())', $replacementId, $effectiveFrom)];
            for ($msc = 5500; $msc <= 35000; $msc += 500) {
                $from = $msc - 250;
                $to = $msc === 35000 ? 'NULL' : number_format($msc + 249.99, 2, '.', '');
                $ecp = $msc <= 14500 ? 10 : 30;
                $rows[] = sprintf(
                    '(%d, %.2F, %s, %.2F, %.2F, \'%s\', NULL, NOW(), NOW())',
                    $replacementId,
                    $from,
                    $to,
                    $msc * 0.05,
                    ($msc * 0.10) + $ecp,
                    $effectiveFrom
                );
            }

            $this->execute(
                'INSERT INTO sss_bracket
                    (contribution_policy_id, salary_from, salary_to, employee_share, employer_share,
                     effective_from, effective_to, created_at, updated_at) VALUES '
                . implode(', ', $rows)
            );
        }

        $this->execute(
            "UPDATE contribution_policy_version
                SET status = 'Retired', updated_at = NOW()
              WHERE policy_code = 'PH-STATUTORY-2026'
                AND status = 'Approved'
                AND contribution_policy_id <> {$replacementId}"
        );
    }

    public function down(): void
    {
        $adapter = $this->getAdapter();
        $adapter->beginTransaction();

        try {
            $this->reverseCorrection();
            $adapter->commitTransaction();
        } catch (\Throwable $exception) {
            $adapter->rollbackTransaction();

            throw $exception;
        }
    }

    private function reverseCorrection(): void
    {
        $replacement = $this->fetchRow(
            "SELECT contribution_policy_id FROM contribution_policy_version
              WHERE policy_code = 'PH-STATUTORY-2026' AND version = 'V3' LIMIT 1"
        );
        if ($replacement === false) {
            return;
        }

        $replacementId = (int) $replacement['contribution_policy_id'];
        $usage = $this->fetchRow(
            "SELECT COUNT(*) AS record_count FROM contribution_record
              WHERE contribution_policy_id = {$replacementId}"
        );
        if ((int) ($usage['record_count'] ?? 0) > 0) {
            throw new RuntimeException('Cannot revert an SSS ECP policy that has been used by payroll.');
        }

        $previous = $this->fetchRow(
            "SELECT contribution_policy_id
               FROM contribution_policy_version
              WHERE policy_code = 'PH-STATUTORY-2026'
                AND contribution_policy_id <> {$replacementId}
                AND status = 'Retired'
              ORDER BY contribution_policy_id DESC
              LIMIT 1"
        );
        if ($previous === false) {
            throw new RuntimeException('Cannot safely restore the prior 2026 statutory policy.');
        }
        $previousId = (int) $previous['contribution_policy_id'];

        $this->execute("DELETE FROM sss_bracket WHERE contribution_policy_id = {$replacementId}");
        $this->execute("DELETE FROM philhealth_rate WHERE contribution_policy_id = {$replacementId}");
        $this->execute("DELETE FROM pagibig_rate WHERE contribution_policy_id = {$replacementId}");
        $this->execute("DELETE FROM contribution_policy_version WHERE contribution_policy_id = {$replacementId}");
        $this->execute(
            "UPDATE contribution_policy_version
                SET status = 'Approved', updated_at = NOW()
              WHERE contribution_policy_id = {$previousId}"
        );
    }
}
