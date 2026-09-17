<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds an auditable cancellation state for unapproved payroll runs.
 *
 * A cancelled run remains visible as history but does not block a corrected
 * replacement run for the same payroll period and branch.
 */
final class AddPayrollRunCancellation extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "ALTER TABLE payroll_run MODIFY status ENUM(
                'Draft', 'Computed', 'PendingOwnerApproval', 'Approved', 'Returned', 'Cancelled'
            ) NOT NULL DEFAULT 'Draft'"
        );

        $table = $this->table('payroll_run');
        if (!$table->hasColumn('cancellation_reason')) {
            $table
                ->addColumn('cancellation_reason', 'string', ['limit' => 1000, 'null' => true, 'default' => null])
                ->addColumn('cancelled_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
                ->addColumn('cancelled_at', 'datetime', ['null' => true, 'default' => null])
                ->addForeignKey('cancelled_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
                ->update();
        }

        // An existing child foreign key uses the original leading columns. Keep
        // a non-unique supporting index before removing its unique form.
        if (!$table->hasIndexByName('idx_run_period_branch')) {
            $this->execute('ALTER TABLE payroll_run ADD INDEX idx_run_period_branch (payroll_period_id, branch_id)');
        }
        if ($table->hasIndexByName('uq_run_period_branch')) {
            $this->execute('ALTER TABLE payroll_run DROP INDEX uq_run_period_branch');
        }
        // NULL values remain distinct in MySQL unique indexes. Clearing this
        // marker on cancellation permits any number of retained cancelled runs
        // while enforcing one active run for a period/branch pair.
        $this->execute(
            'ALTER TABLE payroll_run '
            . 'ADD COLUMN active_run_marker TINYINT UNSIGNED NULL DEFAULT 1, '
            . 'ADD UNIQUE INDEX uq_active_run_period_branch '
            . '(payroll_period_id, branch_id, active_run_marker)'
        );
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE payroll_run DROP INDEX uq_active_run_period_branch');
        $this->execute('ALTER TABLE payroll_run DROP COLUMN active_run_marker');
        $this->table('payroll_run')
            ->dropForeignKey('cancelled_by')
            ->removeColumn('cancellation_reason')
            ->removeColumn('cancelled_by')
            ->removeColumn('cancelled_at')
            ->update();
        $this->execute(
            "ALTER TABLE payroll_run MODIFY status ENUM(
                'Draft', 'Computed', 'PendingOwnerApproval', 'Approved', 'Returned'
            ) NOT NULL DEFAULT 'Draft'"
        );
        $this->execute('ALTER TABLE payroll_run DROP INDEX idx_run_period_branch');
        $this->execute('ALTER TABLE payroll_run ADD UNIQUE INDEX uq_run_period_branch (payroll_period_id, branch_id)');
    }
}
