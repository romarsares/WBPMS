<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Extends the holiday_calendar.holiday_type enum to include
 * 'SpecialNonWorking' (Special Non-Working Holiday, 130% when worked)
 * and corrects the pay_multiplier CHECK constraint accordingly.
 *
 * Before: holiday_type IN ('Regular', 'Special')
 *         Special pay_multiplier = 1.30 (was incorrectly labelled)
 *
 * After:  holiday_type IN ('Regular', 'Special', 'SpecialNonWorking')
 *         Regular           → pay_multiplier = 2.00
 *         Special           → pay_multiplier = 1.00  (Special Working Holiday)
 *         SpecialNonWorking → pay_multiplier = 1.30  (Special Non-Working Holiday)
 *
 * ADR-0001 §Holiday Pay | REQ047
 */
final class AddSpecialNonWorkingHolidayType extends AbstractMigration
{
    public function up(): void
    {
        // 1. Drop the existing CHECK constraint before altering the column/adding new one.
        //    MySQL 8.0+ supports DROP CHECK; constraint name matches the one created in
        //    migration 20260831000002.
        $this->execute(
            "ALTER TABLE holiday_calendar DROP CONSTRAINT chk_holiday_multiplier"
        );

        // 2. Extend the enum to include the new value.
        //    MySQL ALTER COLUMN on an ENUM appends the value without rebuilding existing rows.
        $this->execute(
            "ALTER TABLE holiday_calendar
             MODIFY COLUMN holiday_type ENUM('Regular','Special','SpecialNonWorking')
             NOT NULL"
        );

        // 3. Correct existing Special rows: pay_multiplier was 1.30 (non-working rate)
        //    but Special (Working) should be 1.00.
        $this->execute(
            "UPDATE holiday_calendar
             SET pay_multiplier = 1.00
             WHERE holiday_type = 'Special' AND pay_multiplier = 1.30"
        );

        // 4. Re-add the CHECK constraint covering all three types.
        $this->execute(
            "ALTER TABLE holiday_calendar ADD CONSTRAINT chk_holiday_multiplier
             CHECK (
                 (holiday_type = 'Regular'           AND pay_multiplier = 2.00)
              OR (holiday_type = 'Special'           AND pay_multiplier = 1.00)
              OR (holiday_type = 'SpecialNonWorking' AND pay_multiplier = 1.30)
             )"
        );
    }

    public function down(): void
    {
        // 1. Drop the updated constraint.
        $this->execute(
            "ALTER TABLE holiday_calendar DROP CONSTRAINT chk_holiday_multiplier"
        );

        // 2. Revert SpecialNonWorking rows to Special before narrowing the enum.
        $this->execute(
            "UPDATE holiday_calendar
             SET holiday_type = 'Special', pay_multiplier = 1.30
             WHERE holiday_type = 'SpecialNonWorking'"
        );

        // 3. Restore Special rows' multiplier to the original (incorrect) 1.30.
        $this->execute(
            "UPDATE holiday_calendar
             SET pay_multiplier = 1.30
             WHERE holiday_type = 'Special' AND pay_multiplier = 1.00"
        );

        // 4. Narrow enum back to two values.
        $this->execute(
            "ALTER TABLE holiday_calendar
             MODIFY COLUMN holiday_type ENUM('Regular','Special')
             NOT NULL"
        );

        // 5. Restore original two-type CHECK constraint.
        $this->execute(
            "ALTER TABLE holiday_calendar ADD CONSTRAINT chk_holiday_multiplier
             CHECK (
                 (holiday_type = 'Regular' AND pay_multiplier = 2.00)
              OR (holiday_type = 'Special' AND pay_multiplier = 1.30)
             )"
        );
    }
}
