<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Make Friday-through-Thursday the active payroll calendar again.
 *
 * Existing Sunday-through-Friday periods are retained as historical records.
 * New periods use FridayThursday: Friday through the following Thursday,
 * paid on the following Friday.
 */
final class RestoreFridayThursdayPayrollPeriod extends AbstractMigration
{
    public function up(): void
    {
        // Preserve both historical patterns while introducing the active one.
        $this->execute(
            "ALTER TABLE payroll_period
             MODIFY COLUMN cutoff_pattern
             ENUM('LegacyFridayThursday', 'SundayFriday', 'FridayThursday')
             NOT NULL DEFAULT 'SundayFriday'"
        );

        // Normalise the former Friday-through-Thursday label before removing it
        // from the enum. Its dates are not changed.
        $this->execute(
            "UPDATE payroll_period
                SET cutoff_pattern = 'FridayThursday'
              WHERE cutoff_pattern = 'LegacyFridayThursday'"
        );

        $this->execute('ALTER TABLE payroll_period DROP CHECK chk_period_cutoff_pattern');
        $this->execute(
            "ALTER TABLE payroll_period
             MODIFY COLUMN cutoff_pattern
             ENUM('SundayFriday', 'FridayThursday')
             NOT NULL DEFAULT 'FridayThursday'"
        );

        $this->execute(
            "ALTER TABLE payroll_period
             ADD CONSTRAINT chk_period_cutoff_pattern
             CHECK (
                 (cutoff_pattern = 'FridayThursday'
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
    }

    public function down(): void
    {
        $count = (int) $this->query(
            "SELECT COUNT(*) FROM payroll_period WHERE cutoff_pattern = 'FridayThursday'"
        )->fetchColumn();

        if ($count > 0) {
            throw new RuntimeException(
                'Cannot roll back the Friday-through-Thursday payroll migration while FridayThursday periods exist.'
            );
        }

        $this->execute('ALTER TABLE payroll_period DROP CHECK chk_period_cutoff_pattern');
        $this->execute(
            "ALTER TABLE payroll_period
             MODIFY COLUMN cutoff_pattern
             ENUM('LegacyFridayThursday', 'SundayFriday')
             NOT NULL DEFAULT 'SundayFriday'"
        );
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
    }
}
