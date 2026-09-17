<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration: decouple work_schedule from employee.
 *
 * Before: work_schedule has employee_id FK and is per-employee.
 * After:  work_schedule is a reusable schedule template (no employee_id).
 *         employee_schedule_assignment links employees to schedules.
 *
 * Changes:
 *   1. Add new columns to work_schedule:
 *      - schedule_name (VARCHAR 100, NOT NULL)
 *      - grace_minutes (SMALLINT, default 0)
 *      - overtime_allowed (TINYINT, default 1)
 *      - notes (TEXT, nullable)
 *      - break_start_time (TIME, nullable)
 *      - break_end_time   (TIME, nullable)
 *   2. Populate schedule_name from existing rows (time range).
 *   3. Drop UNIQUE(employee_id, effective_from) index.
 *   4. Drop employee_id FK and column from work_schedule.
 *   5. Add UNIQUE(schedule_name) index on work_schedule.
 *   6. Create employee_schedule_assignment table.
 *   7. Migrate existing per-employee schedule data into
 *      employee_schedule_assignment, deduplicating by schedule pattern.
 */
final class RefactorWorkScheduleDecoupleEmployee extends AbstractMigration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // 1. Add new columns to work_schedule (all nullable or defaulted
        //    so existing rows are not broken during migration).
        // ---------------------------------------------------------------
        $ws = $this->table('work_schedule');
        $ws
            ->addColumn('schedule_name', 'string', [
                'limit'   => 100,
                'null'    => true,   // temporarily nullable; filled below
                'after'   => 'schedule_id',
                'comment' => 'Human-readable name e.g. Regular Shift, Morning Shift',
            ])
            ->addColumn('grace_minutes', 'smallinteger', [
                'signed'  => false,
                'null'    => false,
                'default' => 0,
                'after'   => 'break_minutes',
                'comment' => 'Late grace period in minutes',
            ])
            ->addColumn('overtime_allowed', 'boolean', [
                'null'    => false,
                'default' => true,
                'after'   => 'grace_minutes',
            ])
            ->addColumn('break_start_time', 'time', [
                'null'    => true,
                'default' => null,
                'after'   => 'overtime_allowed',
                'comment' => 'Start of unpaid break e.g. 12:00:00',
            ])
            ->addColumn('break_end_time', 'time', [
                'null'    => true,
                'default' => null,
                'after'   => 'break_start_time',
                'comment' => 'End of unpaid break e.g. 13:00:00',
            ])
            ->addColumn('notes', 'text', [
                'null'  => true,
                'after' => 'break_end_time',
            ])
            ->save();

        // ---------------------------------------------------------------
        // 2. Populate schedule_name from existing rows before making it
        //    NOT NULL. Use a unique name derived from time range + row id
        //    to avoid collisions.
        // ---------------------------------------------------------------
        $this->execute(
            "UPDATE work_schedule
             SET schedule_name = CONCAT(
                 TIME_FORMAT(work_start_time, '%h:%i %p'),
                 ' – ',
                 TIME_FORMAT(work_end_time, '%h:%i %p'),
                 ' (', schedule_id, ')'
             )
             WHERE schedule_name IS NULL"
        );

        // Make schedule_name NOT NULL now that all rows have a value.
        $this->execute(
            "ALTER TABLE work_schedule
             MODIFY COLUMN schedule_name VARCHAR(100) NOT NULL
             COMMENT 'Human-readable name e.g. Regular Shift, Morning Shift'"
        );

        // ---------------------------------------------------------------
        // 3. Create employee_schedule_assignment BEFORE dropping employee_id
        //    so we can migrate data while it still exists.
        // ---------------------------------------------------------------
        $this->table('employee_schedule_assignment', [
            'id'          => false,
            'primary_key' => ['assignment_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Links an employee to a reusable work_schedule; replaces work_schedule.employee_id',
        ])
            ->addColumn('assignment_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id',   'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('schedule_id',   'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to',   'date', ['null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Archived'], 'null' => false, 'default' => 'Active'])
            ->addColumn('notes', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'effective_from'], ['unique' => true, 'name' => 'uq_esa_employee_from'])
            ->addForeignKey('employee_id', 'employee',      'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('schedule_id', 'work_schedule', 'schedule_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute(
            "ALTER TABLE employee_schedule_assignment
             ADD CONSTRAINT chk_esa_period
             CHECK (effective_to IS NULL OR effective_to > effective_from)"
        );

        // ---------------------------------------------------------------
        // 4. Migrate existing per-employee schedule rows into the new
        //    assignment table, then remove employee_id from work_schedule.
        // ---------------------------------------------------------------
        $this->execute(
            "INSERT INTO employee_schedule_assignment
                (employee_id, schedule_id, effective_from, effective_to, status, created_at, updated_at)
             SELECT
                employee_id,
                schedule_id,
                effective_from,
                effective_to,
                status,
                created_at,
                updated_at
             FROM work_schedule
             WHERE employee_id IS NOT NULL
             ON DUPLICATE KEY UPDATE
                schedule_id   = VALUES(schedule_id),
                effective_to  = VALUES(effective_to),
                updated_at    = VALUES(updated_at)"
        );

        // ---------------------------------------------------------------
        // 5. Drop the unique index on (employee_id, effective_from) and
        //    the employee_id FK + column from work_schedule.
        // ---------------------------------------------------------------
        $ws = $this->table('work_schedule');
        $ws->dropForeignKey('employee_id')->save();

        $this->execute("ALTER TABLE work_schedule DROP INDEX uq_ws_employee_from");
        $this->execute("ALTER TABLE work_schedule DROP COLUMN employee_id");

        // ---------------------------------------------------------------
        // 6. Add unique index on schedule_name so duplicate names are
        //    rejected at the DB level.
        // ---------------------------------------------------------------
        $this->execute(
            "ALTER TABLE work_schedule
             ADD CONSTRAINT uq_ws_name UNIQUE (schedule_name)"
        );
    }

    public function down(): void
    {
        // Re-add employee_id to work_schedule
        $this->execute(
            "ALTER TABLE work_schedule
             ADD COLUMN employee_id BIGINT UNSIGNED NULL
             AFTER schedule_id"
        );

        // Re-populate from assignment table (best effort)
        $this->execute(
            "UPDATE work_schedule ws
             JOIN employee_schedule_assignment esa ON esa.schedule_id = ws.schedule_id
             SET ws.employee_id = esa.employee_id"
        );

        // Make NOT NULL and restore FK
        $this->execute(
            "UPDATE work_schedule SET employee_id = 0 WHERE employee_id IS NULL"
        );
        $this->execute(
            "ALTER TABLE work_schedule
             MODIFY COLUMN employee_id BIGINT UNSIGNED NOT NULL"
        );
        $this->execute(
            "ALTER TABLE work_schedule
             ADD CONSTRAINT fk_ws_employee
             FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
             ON DELETE RESTRICT ON UPDATE CASCADE"
        );
        $this->execute(
            "ALTER TABLE work_schedule
             ADD CONSTRAINT uq_ws_employee_from UNIQUE (employee_id, effective_from)"
        );

        // Drop new columns
        $this->execute("ALTER TABLE work_schedule DROP INDEX uq_ws_name");
        $this->execute("ALTER TABLE work_schedule DROP COLUMN schedule_name");
        $this->execute("ALTER TABLE work_schedule DROP COLUMN grace_minutes");
        $this->execute("ALTER TABLE work_schedule DROP COLUMN overtime_allowed");
        $this->execute("ALTER TABLE work_schedule DROP COLUMN break_start_time");
        $this->execute("ALTER TABLE work_schedule DROP COLUMN break_end_time");
        $this->execute("ALTER TABLE work_schedule DROP COLUMN notes");

        // Drop assignment table
        $this->table('employee_schedule_assignment')->drop()->save();
    }
}
