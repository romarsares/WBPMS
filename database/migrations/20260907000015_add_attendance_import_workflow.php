<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds the review lifecycle for parsed attendance imports.
 *
 * Draft batches are stored for HR review but cannot feed payroll. Approved
 * batches are payroll-eligible. Cancelled batches retain their audit trail
 * while their generated attendance is excluded from operational processing.
 */
final class AddAttendanceImportWorkflow extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "ALTER TABLE attendance_import_batch MODIFY status
             ENUM('Processing','Draft','Approved','Completed','Rejected','Cancelled')
             NOT NULL DEFAULT 'Processing'"
        );
        $this->table('attendance_import_batch')
            ->addColumn('approved_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null, 'after' => 'completed_at'])
            ->addColumn('approved_at', 'datetime', ['null' => true, 'default' => null, 'after' => 'approved_by'])
            ->addColumn('cancelled_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null, 'after' => 'approved_at'])
            ->addColumn('cancelled_at', 'datetime', ['null' => true, 'default' => null, 'after' => 'cancelled_by'])
            ->addColumn('cancellation_reason', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'after' => 'cancelled_at'])
            ->addForeignKey('approved_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('cancelled_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();

        $this->execute(
            "ALTER TABLE attendance MODIFY status
             ENUM('Complete','Incomplete','ReviewRequired','Approved','Cancelled')
             NOT NULL DEFAULT 'Complete'"
        );
    }

    public function down(): void
    {
        $this->table('attendance_import_batch')
            ->dropForeignKey('approved_by')
            ->dropForeignKey('cancelled_by')
            ->removeColumn('approved_by')
            ->removeColumn('approved_at')
            ->removeColumn('cancelled_by')
            ->removeColumn('cancelled_at')
            ->removeColumn('cancellation_reason')
            ->update();

        $this->execute(
            "ALTER TABLE attendance_import_batch MODIFY status
             ENUM('Processing','Completed','Rejected') NOT NULL DEFAULT 'Processing'"
        );
        $this->execute(
            "ALTER TABLE attendance MODIFY status
             ENUM('Complete','Incomplete','ReviewRequired','Approved') NOT NULL DEFAULT 'Complete'"
        );
    }
}
