<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Change new payroll periods to the company's Sunday-to-Friday cycle.
 *
 * Existing Friday-to-Thursday periods remain immutable historical records.
 * New periods use cutoff_pattern = SundayFriday, which prevents a migration
 * from rewriting the attendance boundaries of already-created payroll runs.
 */
final class UpdatePayrollPeriodToSundayFriday extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "ALTER TABLE payroll_period
             ADD COLUMN cutoff_pattern ENUM('LegacyFridayThursday', 'SundayFriday')
             NOT NULL DEFAULT 'LegacyFridayThursday' AFTER status"
        );

        $this->execute('ALTER TABLE payroll_period DROP CHECK chk_period_start_friday');
        $this->execute('ALTER TABLE payroll_period DROP CHECK chk_period_end_thursday');
        $this->execute('ALTER TABLE payroll_period DROP CHECK chk_period_seven_days');
        $this->execute('ALTER TABLE payroll_period DROP CHECK chk_period_pay_date');

        $this->execute(
            "ALTER TABLE payroll_period
             ADD CONSTRAINT chk_period_cutoff_pattern
             CHECK (
                 (cutoff_pattern = 'LegacyFridayThursday'
                  AND DAYOFWEEK(period_start) = 6
                  AND DAYOFWEEK(period_end) = 5
                  AND DATEDIFF(period_end, period_start) = 6
                  AND pay_date = DATE_ADD(period_end, INTERVAL 1 DAY))
                 OR
                 (cutoff_pattern = 'SundayFriday'
                  AND DAYOFWEEK(period_start) = 1
                  AND DAYOFWEEK(period_end) = 6
                  AND DATEDIFF(period_end, period_start) = 5
                  AND pay_date = period_end)
             )"
        );

        // Existing rows retain their legacy pattern; all future raw inserts
        // default to the active company schedule.
        $this->execute(
            "ALTER TABLE payroll_period
             MODIFY COLUMN cutoff_pattern ENUM('LegacyFridayThursday', 'SundayFriday')
             NOT NULL DEFAULT 'SundayFriday'"
        );
    }

    public function down(): void
    {
        $count = (int) $this->query(
            "SELECT COUNT(*) FROM payroll_period WHERE cutoff_pattern = 'SundayFriday'"
        )->fetchColumn();

        if ($count > 0) {
            throw new RuntimeException(
                'Cannot roll back the Sunday-to-Friday payroll migration while SundayFriday periods exist.'
            );
        }

        $this->execute('ALTER TABLE payroll_period DROP CHECK chk_period_cutoff_pattern');
        $this->execute('ALTER TABLE payroll_period DROP COLUMN cutoff_pattern');
        $this->execute("ALTER TABLE payroll_period ADD CONSTRAINT chk_period_start_friday CHECK (DAYOFWEEK(period_start) = 6)");
        $this->execute("ALTER TABLE payroll_period ADD CONSTRAINT chk_period_end_thursday CHECK (DAYOFWEEK(period_end) = 5)");
        $this->execute("ALTER TABLE payroll_period ADD CONSTRAINT chk_period_seven_days CHECK (DATEDIFF(period_end, period_start) = 6)");
        $this->execute("ALTER TABLE payroll_period ADD CONSTRAINT chk_period_pay_date CHECK (pay_date = DATE_ADD(period_end, INTERVAL 1 DAY))");
    }
}
