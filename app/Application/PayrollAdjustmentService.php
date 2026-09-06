<?php

declare(strict_types=1);

namespace Wbpms\Application;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Wbpms\Infrastructure\Database\Connection;

/** Applies auditable manual earnings and deductions to a computed payroll run. */
final class PayrollAdjustmentService
{
    public function __construct(private Connection $connection) {}

    /** @return array<string,mixed> */
    public function findForAdjustment(int $runId, int $payrollId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT p.payroll_id, p.payroll_run_id, p.gross_pay, p.total_deductions, p.net_pay,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name, e.employee_number,
                    pr.status AS run_status, pr.branch_id
               FROM payroll p
               JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
               JOIN employee e ON e.employee_id = p.employee_id
              WHERE p.payroll_id = :payroll_id AND p.payroll_run_id = :run_id"
        );
        $stmt->execute([':payroll_id' => $payrollId, ':run_id' => $runId]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payroll) {
            throw new RuntimeException('Payroll employee record not found.');
        }
        return $payroll;
    }

    /**
     * @param array{adjustment_kind:string,amount:string,reason:string} $input
     */
    public function adjust(int $runId, int $payrollId, array $input, int $actingUserId): void
    {
        if ($actingUserId < 1) {
            throw new RuntimeException('A signed-in HR user is required to adjust payroll.');
        }
        $kind = $input['adjustment_kind'];
        if (!in_array($kind, ['earning', 'deduction'], true)) {
            throw new RuntimeException('Choose whether this is an additional pay or a deduction.');
        }
        $amount = $this->parseAmount($input['amount']);
        $reason = trim($input['reason']);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new RuntimeException('Provide an adjustment reason of up to 1,000 characters.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($runId, $payrollId, $actingUserId, $kind, $amount, $reason): void {
            $payroll = $this->findForUpdate($pdo, $runId, $payrollId);
            if (!in_array($payroll['run_status'], ['Computed', 'Returned'], true)) {
                throw new RuntimeException('Payroll adjustments are allowed only on computed or returned runs.');
            }

            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $details = json_encode(['source' => 'hr_manual_adjustment', 'reason' => $reason, 'amount' => $amount], JSON_UNESCAPED_SLASHES);
            $earningId = null;
            $deductionId = null;

            if ($kind === 'earning') {
                $stmt = $pdo->prepare(
                    "INSERT INTO payroll_earnings
                        (payroll_id, earning_type, description, quantity, unit_rate, multiplier,
                         amount, calculation_details, created_at)
                     VALUES
                        (:payroll_id, 'ManualAdjustment', :description, 1, :amount, 1,
                         :amount, :details, :created_at)"
                );
                $stmt->execute([
                    ':payroll_id' => $payrollId,
                    ':description' => 'Manual additional pay: ' . $reason,
                    ':amount' => $amount,
                    ':details' => $details,
                    ':created_at' => $now,
                ]);
                $earningId = (int) $pdo->lastInsertId();
                $gross = (float) $payroll['gross_pay'] + $amount;
                $deductions = (float) $payroll['total_deductions'];
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO deduction
                        (payroll_id, deduction_type, description, quantity, unit_rate,
                         amount, calculation_details, created_at)
                     VALUES
                        (:payroll_id, 'ManualAdjustment', :description, 1, :amount,
                         :amount, :details, :created_at)"
                );
                $stmt->execute([
                    ':payroll_id' => $payrollId,
                    ':description' => 'Manual deduction: ' . $reason,
                    ':amount' => $amount,
                    ':details' => $details,
                    ':created_at' => $now,
                ]);
                $deductionId = (int) $pdo->lastInsertId();
                $gross = (float) $payroll['gross_pay'];
                $deductions = (float) $payroll['total_deductions'] + $amount;
            }

            $net = max(0, round($gross - $deductions, 2));
            $pdo->prepare(
                "UPDATE payroll
                    SET gross_pay = :gross, total_deductions = :deductions, net_pay = :net
                  WHERE payroll_id = :payroll_id"
            )->execute([
                ':gross' => round($gross, 2),
                ':deductions' => round($deductions, 2),
                ':net' => $net,
                ':payroll_id' => $payrollId,
            ]);

            $pdo->prepare(
                "INSERT INTO payroll_adjustment
                    (payroll_run_id, payroll_id, adjustment_type, earning_id, deduction_id,
                     amount, reason, adjusted_by, adjusted_at)
                 VALUES
                    (:run_id, :payroll_id, :type, :earning_id, :deduction_id,
                     :amount, :reason, :adjusted_by, :adjusted_at)"
            )->execute([
                ':run_id' => $runId,
                ':payroll_id' => $payrollId,
                ':type' => $kind,
                ':earning_id' => $earningId,
                ':deduction_id' => $deductionId,
                ':amount' => $amount,
                ':reason' => $reason,
                ':adjusted_by' => $actingUserId,
                ':adjusted_at' => $now,
            ]);

            $totals = $pdo->prepare(
                "SELECT COALESCE(SUM(gross_pay), 0) AS gross,
                        COALESCE(SUM(total_deductions), 0) AS deductions,
                        COALESCE(SUM(net_pay), 0) AS net
                   FROM payroll WHERE payroll_run_id = :run_id"
            );
            $totals->execute([':run_id' => $runId]);
            $runTotals = $totals->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare(
                "UPDATE payroll_run
                    SET status = 'Computed', gross_pay = :gross, total_deductions = :dedctions,
                        net_pay = :net, updated_at = :updated_at
                  WHERE payroll_run_id = :run_id"
            )->execute([
                ':gross' => $runTotals['gross'],
                ':dedctions' => $runTotals['deductions'],
                ':net' => $runTotals['net'],
                ':updated_at' => $now,
                ':run_id' => $runId,
            ]);

            $description = json_encode(['type' => $kind, 'amount' => $amount, 'reason' => $reason], JSON_UNESCAPED_SLASHES);
            $pdo->prepare(
                "INSERT INTO audit_logs
                    (user_id, event_type, action_performed, table_affected, record_id,
                     description, action_at, created_at)
                 VALUES
                    (:user_id, 'payroll_adjusted', 'add_manual_payroll_adjustment', 'payroll', :record_id,
                     :description, :action_at, :created_at)"
            )->execute([
                ':user_id' => $actingUserId,
                ':record_id' => $payrollId,
                ':description' => $description,
                ':action_at' => $now,
                ':created_at' => $now,
            ]);
        });
    }

    /** @return array<string,mixed> */
    private function findForUpdate(PDO $pdo, int $runId, int $payrollId): array
    {
        $stmt = $pdo->prepare(
            "SELECT p.payroll_id, p.gross_pay, p.total_deductions, pr.status AS run_status
               FROM payroll p
               JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
              WHERE p.payroll_id = :payroll_id AND p.payroll_run_id = :run_id
              FOR UPDATE"
        );
        $stmt->execute([':payroll_id' => $payrollId, ':run_id' => $runId]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payroll) {
            throw new RuntimeException('Payroll employee record not found.');
        }
        return $payroll;
    }

    private function parseAmount(string $value): float
    {
        $value = trim($value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value) || (float) $value <= 0 || (float) $value > 1000000) {
            throw new RuntimeException('Amount must be greater than zero and no more than ₱1,000,000.00.');
        }
        return round((float) $value, 2);
    }
}
