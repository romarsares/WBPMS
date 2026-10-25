<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 025: Ensure payroll_run cancellation schema is fully applied.
 *
 * Root cause (BUG-3):
 *   Migration 20260907000014_add_payroll_run_cancellation was not executed on
 *   the production (Railway) database. As a result:
 *     - payroll_run.status ENUM was still ('Draft','Computed','PendingOwnerApproval','Approved','Returned')
 *       — 'Cancelled' was missing.
 *     - Columns cancellation_reason, cancelled_by, cancelled_at were absent.
 *     - Column active_run_marker and index uq_active_run_period_branch were absent.
 *
 *   When PayrollService::cancelRun() wrote status = 'Cancelled', MySQL raised:
 *     SQLSTATE[01000] Warning 1265: Data truncated for column 'status' at row 1
 *
 * Fix: Re-apply every change from migration 014 idempotently — each step checks
 *   whether the schema already matches before attempting the ALTER so the migration
 *   is safe to run even on databases where 014 already executed.
 *
 * REQ047 — Payroll lifecycle; Cancel Payroll Run action.
 */
final class EnsurePayrollRunCancellationSchema extends AbstractMigration
{
    public function up(): void
    {
        // -----------------------------------------------------------------------
        // 1. Expand the status ENUM to include 'Cancelled'.
        //    Always re-issue the full MODIFY so the definition is authoritative
        //    regardless of whether 014 ran. MySQL ignores ENUM changes that
        //    produce an identical column definition.
        // -----------------------------------------------------------------------
        $this->execute(
            "ALTER TABLE `payroll_run`
             MODIFY COLUMN `status`
                ENUM('Draft','Computed','PendingOwnerApproval','Approved','Returned','Cancelled')
                NOT NULL
                DEFAULT 'Draft'"
        );

        // -----------------------------------------------------------------------
        // 2. Add cancellation audit columns if they are missing.
        // -----------------------------------------------------------------------
        $table = $this->table('payroll_run');

        if (!$table->hasColumn('cancellation_reason')) {
            $table->addColumn(
                'cancellation_reason',
                'string',
                ['limit' => 1000, 'null' => true, 'default' => null]
            )->update();
        }

        if (!$table->hasColumn('cancelled_by')) {
            $table->addColumn(
                'cancelled_by',
                'biginteger',
                ['signed' => false, 'null' => true, 'default' => null]
            )->addForeignKey(
                'cancelled_by',
                'users',
                'user_id',
                ['delete' => 'SET NULL', 'update' => 'CASCADE']
            )->update();
        }

        if (!$table->hasColumn('cancelled_at')) {
            $table->addColumn(
                'cancelled_at',
                'datetime',
                ['null' => true, 'default' => null]
            )->update();
        }

        // -----------------------------------------------------------------------
        // 3. Replace the period/branch unique index with the partial-unique
        //    active_run_marker approach introduced in migration 014.
        //    This allows multiple cancelled runs for the same period+branch while
        //    enforcing that only one active (non-cancelled) run exists per pair.
        // -----------------------------------------------------------------------

        // Add supporting non-unique index first (guards the FK child reference).
        if (!$table->hasIndexByName('idx_run_period_branch')) {
            $this->execute(
                'ALTER TABLE `payroll_run`
                 ADD INDEX `idx_run_period_branch` (payroll_period_id, branch_id)'
            );
        }

        // Drop the old unique index only if it still exists.
        if ($table->hasIndexByName('uq_run_period_branch')) {
            $this->execute('ALTER TABLE `payroll_run` DROP INDEX `uq_run_period_branch`');
        }

        // Add the marker column and partial-unique index.
        if (!$table->hasColumn('active_run_marker')) {
            $this->execute(
                'ALTER TABLE `payroll_run`
                 ADD COLUMN `active_run_marker` TINYINT UNSIGNED NULL DEFAULT 1'
            );
        }

        if (!$table->hasIndexByName('uq_active_run_period_branch')) {
            $this->execute(
                'ALTER TABLE `payroll_run`
                 ADD UNIQUE INDEX `uq_active_run_period_branch`
                 (payroll_period_id, branch_id, active_run_marker)'
            );
        }
    }

    public function down(): void
    {
        // Revert the status ENUM (removes 'Cancelled').
        $this->execute(
            "ALTER TABLE `payroll_run`
             MODIFY COLUMN `status`
                ENUM('Draft','Computed','PendingOwnerApproval','Approved','Returned')
                NOT NULL
                DEFAULT 'Draft'"
        );

        // Restore the original unique index.
        $this->execute('ALTER TABLE `payroll_run` DROP INDEX `uq_active_run_period_branch`');
        $this->execute('ALTER TABLE `payroll_run` DROP COLUMN `active_run_marker`');

        $this->table('payroll_run')
            ->dropForeignKey('cancelled_by')
            ->removeColumn('cancellation_reason')
            ->removeColumn('cancelled_by')
            ->removeColumn('cancelled_at')
            ->update();

        $this->execute('ALTER TABLE `payroll_run` DROP INDEX `idx_run_period_branch`');
        $this->execute(
            'ALTER TABLE `payroll_run`
             ADD UNIQUE INDEX `uq_run_period_branch` (payroll_period_id, branch_id)'
        );
    }
}
