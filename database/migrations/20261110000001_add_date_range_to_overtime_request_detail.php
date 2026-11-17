<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 20261110000001 — replace overtime_date with date_from / date_to on overtime_request_detail.
 *
 * Up:
 *   1. Add date_from DATE NOT NULL (default same as overtime_date to satisfy NOT NULL during ALTER).
 *   2. Add date_to   DATE NOT NULL (default same as overtime_date).
 *   3. Back-fill date_from / date_to from existing overtime_date rows.
 *   4. Drop the default now that the column is populated.
 *   5. Add CHECK constraint: date_to >= date_from.
 *   6. Drop overtime_date column.
 *   7. Update requested_minutes semantics: now represents minutes per day
 *      (start_time → end_time window); total OT minutes across the range is
 *      computed dynamically in PayrollService.
 *
 * Down: reverses — adds overtime_date back, back-fills from date_from, drops date_from / date_to.
 */
final class AddDateRangeToOvertimeRequestDetail extends AbstractMigration
{
    public function up(): void
    {
        // 1 & 2 — add columns with a temporary default so NOT NULL is satisfied immediately.
        $this->execute(
            "ALTER TABLE overtime_request_detail
                ADD COLUMN date_from DATE NOT NULL DEFAULT '1970-01-01' AFTER request_id,
                ADD COLUMN date_to   DATE NOT NULL DEFAULT '1970-01-01' AFTER date_from"
        );

        // 3 — back-fill from existing overtime_date.
        $this->execute(
            "UPDATE overtime_request_detail SET date_from = overtime_date, date_to = overtime_date"
        );

        // 4 — remove the temporary default.
        $this->execute(
            "ALTER TABLE overtime_request_detail
                ALTER COLUMN date_from DROP DEFAULT,
                ALTER COLUMN date_to   DROP DEFAULT"
        );

        // 5 — add range CHECK constraint.
        $this->execute(
            "ALTER TABLE overtime_request_detail
                ADD CONSTRAINT chk_ord_date_range CHECK (date_to >= date_from)"
        );

        // 6 — drop the old single-date column.
        $this->execute(
            "ALTER TABLE overtime_request_detail DROP COLUMN overtime_date"
        );
    }

    public function down(): void
    {
        // Re-add overtime_date, back-fill from date_from, drop date_from / date_to.
        $this->execute(
            "ALTER TABLE overtime_request_detail
                ADD COLUMN overtime_date DATE NOT NULL DEFAULT '1970-01-01' AFTER request_id"
        );

        $this->execute(
            "UPDATE overtime_request_detail SET overtime_date = date_from"
        );

        $this->execute(
            "ALTER TABLE overtime_request_detail ALTER COLUMN overtime_date DROP DEFAULT"
        );

        $this->execute(
            "ALTER TABLE overtime_request_detail
                DROP CONSTRAINT chk_ord_date_range,
                DROP COLUMN date_from,
                DROP COLUMN date_to"
        );
    }
}
