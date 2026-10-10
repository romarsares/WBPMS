<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 023: Make attendance.schedule_id nullable.
 *
 * An employee may have punch records on a date before a schedule assignment
 * has been configured (e.g. a newly created employee record, a gap between
 * schedule periods, or an import that pre-dates schedule data entry).
 *
 * Previously, schedule_id was NOT NULL with a FK to work_schedule. The
 * attendance import crashed with SQLSTATE[23000] when the employee had no
 * active schedule assignment on the punch date because NULL was passed to
 * a NOT NULL column.
 *
 * Fix: allow NULL on attendance.schedule_id. The import service falls back
 * to a standard 07:00–16:00 default for timesheet calculation when no
 * schedule is found. HR should assign a proper schedule to the employee and
 * re-run the import or manually correct the attendance row afterwards.
 *
 * The FK to work_schedule is retained (with ON DELETE SET NULL so that
 * archiving a schedule does not destroy attendance history).
 */
final class MakeAttendanceScheduleIdNullable extends AbstractMigration
{
    public function up(): void
    {
        // Discover and drop the existing FK on attendance.schedule_id without
        // hardcoding the Phinx-generated constraint name.
        foreach ($this->findScheduleFkNames() as $fkName) {
            $this->execute("ALTER TABLE `attendance` DROP FOREIGN KEY `{$fkName}`");
        }

        $this->execute(
            "ALTER TABLE `attendance`
               MODIFY COLUMN `schedule_id` BIGINT UNSIGNED NULL DEFAULT NULL
                 COMMENT 'NULL when no schedule assignment exists for this employee on this date'"
        );

        $this->execute(
            "ALTER TABLE `attendance`
               ADD CONSTRAINT `fk_attendance_schedule_id`
               FOREIGN KEY (`schedule_id`) REFERENCES `work_schedule` (`schedule_id`)
               ON DELETE SET NULL ON UPDATE CASCADE"
        );
    }

    public function down(): void
    {
        // Revert: clear nulls first (lossy; only safe on a fresh dev database).
        $this->execute(
            "ALTER TABLE `attendance` DROP FOREIGN KEY `fk_attendance_schedule_id`"
        );

        // Set nulls to a placeholder (will break FK if 1 does not exist; intended
        // only for rollback on a freshly-migrated dev database with seeded data).
        $this->execute(
            "UPDATE `attendance` SET `schedule_id` = 1 WHERE `schedule_id` IS NULL"
        );

        $this->execute(
            "ALTER TABLE `attendance`
               MODIFY COLUMN `schedule_id` BIGINT UNSIGNED NOT NULL"
        );

        $this->execute(
            "ALTER TABLE `attendance`
               ADD CONSTRAINT `fk_attendance_schedule_id`
               FOREIGN KEY (`schedule_id`) REFERENCES `work_schedule` (`schedule_id`)
               ON DELETE RESTRICT ON UPDATE CASCADE"
        );
    }

    /**
     * Return the FK constraint name(s) on attendance.schedule_id → work_schedule
     * by querying INFORMATION_SCHEMA. Normally returns exactly one name.
     *
     * @return list<string>
     */
    private function findScheduleFkNames(): array
    {
        $pdo  = $this->getAdapter()->getConnection();
        $stmt = $pdo->query(
            "SELECT CONSTRAINT_NAME
               FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA            = DATABASE()
                AND TABLE_NAME             = 'attendance'
                AND COLUMN_NAME            = 'schedule_id'
                AND REFERENCED_TABLE_NAME  = 'work_schedule'"
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
