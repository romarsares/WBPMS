<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration: Add 'Absence' to deduction.deduction_type ENUM.
 *
 * Root cause (BUG-2):
 *   PayrollService::computeRun() calls insertDeduction() with type 'Absence'
 *   for employees who have unpaid absence days. However, the original migration
 *   (20260831000005) defined deduction.deduction_type as:
 *     ENUM('Late','Undertime','CashAdvance','SSS','PhilHealth','PagIBIG','IncomeTax')
 *   'Absence' was not included.
 *
 *   MySQL raised SQLSTATE[01000] Warning 1265: Data truncated for column
 *   'deduction_type' (shown in the UI as "status" due to MySQL's generic
 *   truncation message referencing the first affected column positionally),
 *   silently truncating the value to '' and potentially writing a zero-amount
 *   deduction row with no valid type.
 *
 * Fix: ALTER the column to include 'Absence'.
 *   Existing rows are all valid enum members; no data correction needed.
 *
 * REQ047 — Payroll computation; unpaid absence deduction lines.
 */
final class AddAbsenceToDeductionType extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "ALTER TABLE `deduction`
             MODIFY COLUMN `deduction_type`
                ENUM('Late','Undertime','Absence','CashAdvance','SSS','PhilHealth','PagIBIG','IncomeTax')
                NOT NULL
                COMMENT 'Absence = unpaid day already excluded from Basic Pay; recorded for audit'"
        );
    }

    public function down(): void
    {
        // Revert to original. Any 'Absence' rows will be truncated —
        // acceptable only when rolling back in a development environment.
        $this->execute(
            "ALTER TABLE `deduction`
             MODIFY COLUMN `deduction_type`
                ENUM('Late','Undertime','CashAdvance','SSS','PhilHealth','PagIBIG','IncomeTax')
                NOT NULL"
        );
    }
}
