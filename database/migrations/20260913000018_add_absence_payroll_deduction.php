<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Makes unpaid absence an explicit payroll policy line. Its amount remains
 * zero because an absent day is excluded from Basic Pay before deductions are
 * totaled; the line exists for HR and payslip auditability.
 */
final class AddAbsencePayrollDeduction extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "ALTER TABLE deduction MODIFY deduction_type
             ENUM('Late','Undertime','Absence','CashAdvance','SSS','PhilHealth','PagIBIG','IncomeTax','ManualAdjustment')
             NOT NULL"
        );
    }

    public function down(): void
    {
        $this->execute(
            "ALTER TABLE deduction MODIFY deduction_type
             ENUM('Late','Undertime','CashAdvance','SSS','PhilHealth','PagIBIG','IncomeTax','ManualAdjustment')
             NOT NULL"
        );
    }
}
