<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 003: Attendance Import and Timesheet tables.
 *
 * Creates: attendance_import_batch, biometric_punch, attendance,
 *          attendance_punch, attendance_adjustment, attendance_policy_flag
 *
 * ADR-0001 biometric workbook contract:
 *   - One attendance row per employee per business date.
 *   - All raw punches linked through attendance_punch.
 *   - Duplicate files detected by SHA-256 (file_checksum UQ).
 *   - Duplicate punches detected by (device_id, device_employee_code, source_local_at) UQ.
 *
 * ADR-0002 §4/§5: attendance grain; import batch stores source_year/source_month.
 * Canonical schema v1.1: Attendance import and timesheets section.
 */
final class CreateAttendanceImportTables extends AbstractMigration
{
    public function up(): void
    {
        // -------------------------------------------------------------------
        // attendance_import_batch
        // -------------------------------------------------------------------
        $this->table('attendance_import_batch', [
            'id'          => false,
            'primary_key' => ['import_batch_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'One record per uploaded .xls file; SHA-256 prevents duplicate imports',
        ])
            ->addColumn('import_batch_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('device_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('uploaded_by', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('file_name', 'string', ['limit' => 255, 'null' => false, 'comment' => 'Original client filename (evidence only; not used for storage)'])
            ->addColumn('file_checksum', 'char', ['limit' => 64, 'null' => false, 'comment' => 'SHA-256 hex; unique prevents duplicate imports'])
            ->addColumn('source_year', 'smallinteger', ['signed' => false, 'null' => false])
            ->addColumn('source_month', 'integer', ['signed' => false, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'null' => false])
            ->addColumn('parser_version', 'string', ['limit' => 50, 'null' => false, 'default' => 'LDE_XLS_DAILY_LOG_V1'])
            ->addColumn('status', 'enum', ['values' => ['Processing', 'Completed', 'Rejected'], 'null' => false, 'default' => 'Processing'])
            ->addColumn('records_parsed', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('records_matched', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('records_unmatched', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('duplicates_skipped', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('incomplete_days', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('multi_punch_days', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('uploaded_at', 'datetime', ['null' => false])
            ->addColumn('completed_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['file_checksum'], ['unique' => true, 'name' => 'uq_batch_checksum'])
            ->addForeignKey('device_id', 'biometric_device', 'device_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('uploaded_by', 'users', 'user_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE attendance_import_batch ADD CONSTRAINT chk_batch_month "
            . "CHECK (source_month BETWEEN 1 AND 12)");

        // -------------------------------------------------------------------
        // biometric_punch — immutable raw punch evidence
        // -------------------------------------------------------------------
        $this->table('biometric_punch', [
            'id'          => false,
            'primary_key' => ['punch_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Immutable raw punch; all time tokens from the workbook are preserved',
        ])
            ->addColumn('punch_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('import_batch_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('device_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null, 'comment' => 'NULL when unmatched'])
            ->addColumn('branch_assignment_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('device_employee_code', 'string', ['limit' => 50, 'null' => false, 'comment' => 'Enrollment ID from workbook; leading zeroes preserved'])
            ->addColumn('source_local_at', 'datetime', ['null' => false, 'comment' => 'Asia/Manila local timestamp from workbook'])
            ->addColumn('punched_at_utc', 'datetime', ['null' => false, 'comment' => 'UTC equivalent for stored-instant comparisons'])
            ->addColumn('punch_type', 'enum', ['values' => ['In', 'Out', 'Unknown'], 'null' => true, 'default' => null, 'comment' => 'Inferred by timesheet generator; NULL until classified'])
            ->addColumn('device_transaction_id', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('match_status', 'enum', ['values' => ['matched', 'unmatched', 'coverage_exception', 'duplicate'], 'null' => false])
            ->addColumn('source_department', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'Dept column from workbook; evidence only'])
            ->addColumn('source_user_id', 'string', ['limit' => 50, 'null' => true, 'default' => null, 'comment' => 'User ID column; evidence only'])
            ->addColumn('source_employee_name', 'string', ['limit' => 150, 'null' => true, 'default' => null, 'comment' => 'Name column; evidence only'])
            ->addColumn('raw_record', 'text', ['null' => false, 'comment' => 'Original cell value for audit'])
            ->addColumn('source_workbook_row', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('source_date_column', 'string', ['limit' => 20, 'null' => false, 'comment' => 'Column header e.g. "08/15 Sat"'])
            ->addColumn('resolved_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('resolved_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['device_id', 'device_employee_code', 'source_local_at'], ['unique' => true, 'name' => 'uq_punch_device_code_local_at'])
            ->addIndex(['import_batch_id'], ['name' => 'idx_punch_batch'])
            ->addIndex(['employee_id'], ['name' => 'idx_punch_employee'])
            ->addForeignKey('import_batch_id', 'attendance_import_batch', 'import_batch_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('device_id', 'biometric_device', 'device_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('branch_assignment_id', 'employee_branch_assignment', 'branch_assignment_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('resolved_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // attendance — one row per employee per business date
        // -------------------------------------------------------------------
        $this->table('attendance', [
            'id'          => false,
            'primary_key' => ['attendance_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Daily attendance; unique (employee_id, attendance_date); time values in minutes',
        ])
            ->addColumn('attendance_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('branch_assignment_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('schedule_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('attendance_date', 'date', ['null' => false])
            ->addColumn('time_in', 'time', ['null' => true, 'default' => null, 'comment' => 'NULL when punch is incomplete (only time_out present)'])
            ->addColumn('time_out', 'time', ['null' => true, 'default' => null, 'comment' => 'NULL when punch is incomplete (only time_in present)'])
            ->addColumn('hours_worked_minutes', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('late_minutes', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('undertime_minutes', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('overtime_minutes', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('status', 'enum', ['values' => ['Complete', 'Incomplete', 'ReviewRequired', 'Approved'], 'null' => false, 'default' => 'Complete'])
            ->addColumn('source', 'enum', ['values' => ['xls_import', 'manual'], 'null' => false])
            ->addColumn('import_batch_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null, 'comment' => 'Required for xls_import; NULL for manual'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'attendance_date'], ['unique' => true, 'name' => 'uq_attendance_employee_date'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('branch_assignment_id', 'employee_branch_assignment', 'branch_assignment_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('schedule_id', 'work_schedule', 'schedule_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('import_batch_id', 'attendance_import_batch', 'import_batch_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // NOTE: MySQL 8.0+ does not allow a CHECK constraint to reference a column
        // that also participates in a foreign key referential action.
        // The source/import_batch_id integrity rule is enforced in AttendanceImportService
        // and AttendanceService at the application layer instead.

        // -------------------------------------------------------------------
        // attendance_punch — links attendance rows to raw biometric_punch evidence
        // -------------------------------------------------------------------
        $this->table('attendance_punch', [
            'id'          => false,
            'primary_key' => ['attendance_id', 'punch_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Links each attendance record to its supporting biometric_punch rows',
        ])
            ->addColumn('attendance_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('punch_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('evidence_role', 'enum', ['values' => ['TimeIn', 'Intermediate', 'TimeOut', 'Unclassified'], 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['punch_id'], ['unique' => true, 'name' => 'uq_ap_punch_id'])
            ->addForeignKey('attendance_id', 'attendance', 'attendance_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('punch_id', 'biometric_punch', 'punch_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // attendance_adjustment — immutable change history for HR adjustments
        // -------------------------------------------------------------------
        $this->table('attendance_adjustment', [
            'id'          => false,
            'primary_key' => ['adjustment_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Append-only log of manual attendance corrections',
        ])
            ->addColumn('adjustment_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('attendance_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('adjusted_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('old_time_in', 'time', ['null' => true, 'default' => null])
            ->addColumn('new_time_in', 'time', ['null' => true, 'default' => null])
            ->addColumn('old_time_out', 'time', ['null' => true, 'default' => null])
            ->addColumn('new_time_out', 'time', ['null' => true, 'default' => null])
            ->addColumn('adjustment_type', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('reason', 'string', ['limit' => 1000, 'null' => false])
            ->addColumn('adjustment_at', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('attendance_id', 'attendance', 'attendance_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('adjusted_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // attendance_policy_flag — HR review flags; never auto-terminates
        // -------------------------------------------------------------------
        $this->table('attendance_policy_flag', [
            'id'          => false,
            'primary_key' => ['flag_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Attendance threshold flags for HR review; system never suspends/terminates automatically',
        ])
            ->addColumn('flag_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('flag_type', 'enum', [
                'values'  => ['ConsecutiveLate', 'TardinessMemoCap', 'TwoWeekAbsence', 'ConsecutiveAWOL'],
                'null'    => false,
            ])
            ->addColumn('triggering_date', 'date', ['null' => false])
            ->addColumn('status', 'enum', ['values' => ['Pending', 'Reviewed', 'Closed'], 'null' => false, 'default' => 'Pending'])
            ->addColumn('reviewed_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('reviewed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('action_taken', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'flag_type', 'triggering_date'], ['unique' => true, 'name' => 'uq_apf_employee_type_date'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('reviewed_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }

    public function down(): void
    {
        $tables = [
            'attendance_policy_flag',
            'attendance_adjustment',
            'attendance_punch',
            'attendance',
            'biometric_punch',
            'attendance_import_batch',
        ];

        foreach ($tables as $t) {
            if ($this->hasTable($t)) {
                $this->table($t)->drop()->save();
            }
        }
    }
}
