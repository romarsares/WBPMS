<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Enables append-only HR manual earnings and deductions on unapproved payroll. */
final class AddManualPayrollAdjustments extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE payroll_earnings MODIFY earning_type ENUM('Basic','Overtime','RegularHoliday','SpecialHoliday','ThirteenthMonth','ManualAdjustment') NOT NULL");
        $this->execute("ALTER TABLE deduction MODIFY deduction_type ENUM('Late','Undertime','CashAdvance','SSS','PhilHealth','PagIBIG','IncomeTax','ManualAdjustment') NOT NULL");

        if (!$this->hasTable('payroll_adjustment')) {
            $this->table('payroll_adjustment', [
            'id' => false,
            'primary_key' => ['adjustment_id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Append-only HR manual payroll earnings and deductions',
            ])
            ->addColumn('adjustment_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('payroll_run_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('payroll_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('adjustment_type', 'enum', ['values' => ['earning', 'deduction'], 'null' => false])
            ->addColumn('earning_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('deduction_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('reason', 'string', ['limit' => 1000, 'null' => false])
            ->addColumn('adjusted_by', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('adjusted_at', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['payroll_id'], ['name' => 'idx_payroll_adjustment_payroll'])
            ->addForeignKey('payroll_run_id', 'payroll_run', 'payroll_run_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('payroll_id', 'payroll', 'payroll_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('earning_id', 'payroll_earnings', 'earning_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('deduction_id', 'deduction', 'deduction_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('adjusted_by', 'users', 'user_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
                ->create();
        }

        // MySQL rejects a CHECK over earning_id/deduction_id because both columns
        // participate in foreign keys. PayrollAdjustmentService owns these writes
        // and always writes exactly one linked earning or deduction row.
    }

    public function down(): void
    {
        if ($this->hasTable('payroll_adjustment')) {
            $this->table('payroll_adjustment')->drop()->save();
        }
        $this->execute("ALTER TABLE payroll_earnings MODIFY earning_type ENUM('Basic','Overtime','RegularHoliday','SpecialHoliday','ThirteenthMonth') NOT NULL");
        $this->execute("ALTER TABLE deduction MODIFY deduction_type ENUM('Late','Undertime','CashAdvance','SSS','PhilHealth','PagIBIG','IncomeTax') NOT NULL");
    }
}
