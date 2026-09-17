<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 005: Salary, Policy, Payroll, and Contribution tables.
 *
 * Creates: salary, payroll_policy_version, contribution_policy_version,
 *          sss_bracket, philhealth_rate, pagibig_rate, payroll_period,
 *          payroll_run, payroll, payroll_earnings, deduction,
 *          contribution_record, payslip, cash_advance_repayment
 *
 * ADR-0001 payroll lifecycle: Draft → Computed → PendingOwnerApproval → Approved/Returned
 * ADR-0001 contribution fixture: EEMR = (daily_rate × 313) ÷ 12; last-Friday deduction
 * ADR-0002: payroll_run.status is sole state authority; approved rows immutable.
 * ADR-0002 §16: period checks — Friday start, Thursday end, 7 calendar days.
 * Canonical schema v1.1: Salary, policy, payroll, and contributions section.
 */
final class CreateSalaryPayrollContributionTables extends AbstractMigration
{
    public function up(): void
    {
        // -------------------------------------------------------------------
        // salary — effective-dated daily rate; only authoritative wage source
        // -------------------------------------------------------------------
        $salary = $this->table('salary', [
            'id'          => false,
            'primary_key' => ['salary_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Effective-dated daily rate per employee; new row for every rate change',
        ]);
        $salary
            ->addColumn('salary_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('daily_rate', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Archived'], 'null' => false, 'default' => 'Active'])
            ->addColumn('created_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'effective_from'], ['unique' => true, 'name' => 'uq_salary_employee_from'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('created_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE salary ADD CONSTRAINT chk_salary_period "
            . "CHECK (effective_to IS NULL OR effective_to > effective_from)");
        $this->execute("ALTER TABLE salary ADD CONSTRAINT chk_salary_rate "
            . "CHECK (daily_rate > 0)");

        // -------------------------------------------------------------------
        // payroll_policy_version
        // -------------------------------------------------------------------
        $this->table('payroll_policy_version', [
            'id'          => false,
            'primary_key' => ['policy_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Versioned payroll rules; demo_only=TRUE flags non-production fixtures',
        ])
            ->addColumn('policy_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('policy_code', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('version', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('eemr_days_per_year', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 313, 'comment' => 'EEMR = daily_rate × eemr_days_per_year ÷ 12'])
            ->addColumn('annual_tax_threshold', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false, 'default' => '250000.00'])
            ->addColumn('late_rate_per_minute', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false, 'default' => '1.00', 'comment' => '₱1 per minute per ADR-0001'])
            ->addColumn('rounding_mode', 'enum', ['values' => ['HalfUp'], 'null' => false, 'default' => 'HalfUp'])
            ->addColumn('demo_only', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('status', 'enum', ['values' => ['Draft', 'Approved', 'Retired'], 'null' => false, 'default' => 'Draft'])
            ->addColumn('approved_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('approved_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['policy_code', 'version'], ['unique' => true, 'name' => 'uq_ppv_code_version'])
            ->addForeignKey('approved_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // contribution_policy_version
        // -------------------------------------------------------------------
        $this->table('contribution_policy_version', [
            'id'          => false,
            'primary_key' => ['contribution_policy_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Versioned government contribution policy; brackets/rates reference this',
        ])
            ->addColumn('contribution_policy_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('policy_code', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('version', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('demo_only', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('status', 'enum', ['values' => ['Draft', 'Approved', 'Retired'], 'null' => false, 'default' => 'Draft'])
            ->addColumn('approved_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('approved_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['policy_code', 'version'], ['unique' => true, 'name' => 'uq_cpv_code_version'])
            ->addForeignKey('approved_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // sss_bracket — tiered SSS salary brackets per policy version
        // -------------------------------------------------------------------
        $this->table('sss_bracket', [
            'id'          => false,
            'primary_key' => ['bracket_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'SSS salary brackets; salary_to NULL means open upper bound',
        ])
            ->addColumn('bracket_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('contribution_policy_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('salary_from', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('salary_to', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('employee_share', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('employer_share', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['contribution_policy_id', 'salary_from'], ['unique' => true, 'name' => 'uq_sss_policy_from'])
            ->addForeignKey('contribution_policy_id', 'contribution_policy_version', 'contribution_policy_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // philhealth_rate — one rate row per policy version (UQ on policy)
        // -------------------------------------------------------------------
        $this->table('philhealth_rate', [
            'id'          => false,
            'primary_key' => ['rate_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'PhilHealth rate; 4% total split 50/50 per ADR-0001 demo fixture',
        ])
            ->addColumn('rate_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('contribution_policy_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('rate_decimal', 'decimal', ['precision' => 7, 'scale' => 6, 'null' => false, 'comment' => '0.040000 = 4%'])
            ->addColumn('basis_floor', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('basis_ceiling', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('employee_share_decimal', 'decimal', ['precision' => 7, 'scale' => 6, 'null' => false, 'comment' => '0.020000 = 2% (half of total)'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['contribution_policy_id'], ['unique' => true, 'name' => 'uq_philhealth_policy'])
            ->addForeignKey('contribution_policy_id', 'contribution_policy_version', 'contribution_policy_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // pagibig_rate — one rate row per policy version
        // -------------------------------------------------------------------
        $this->table('pagibig_rate', [
            'id'          => false,
            'primary_key' => ['rate_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Pag-IBIG rate; ₱200 employee + ₱200 employer fixed per ADR-0001 demo',
        ])
            ->addColumn('rate_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('contribution_policy_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('rate_decimal', 'decimal', ['precision' => 7, 'scale' => 6, 'null' => true, 'default' => null])
            ->addColumn('basis_ceiling', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('employee_fixed_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('employer_fixed_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['contribution_policy_id'], ['unique' => true, 'name' => 'uq_pagibig_policy'])
            ->addForeignKey('contribution_policy_id', 'contribution_policy_version', 'contribution_policy_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // Check: either percentage-based (rate_decimal + basis_ceiling) or fixed amounts
        $this->execute("ALTER TABLE pagibig_rate ADD CONSTRAINT chk_pagibig_mode "
            . "CHECK ((rate_decimal IS NOT NULL AND basis_ceiling IS NOT NULL) "
            . "OR (employee_fixed_amount IS NOT NULL AND employer_fixed_amount IS NOT NULL))");

        // -------------------------------------------------------------------
        // payroll_period — weekly Friday-through-Thursday
        // -------------------------------------------------------------------
        $this->table('payroll_period', [
            'id'          => false,
            'primary_key' => ['payroll_period_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Weekly payroll window: starts Friday, ends Thursday, pay date = Friday after end',
        ])
            ->addColumn('payroll_period_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('period_start', 'date', ['null' => false, 'comment' => 'Friday'])
            ->addColumn('period_end', 'date', ['null' => false, 'comment' => 'Thursday (6 days later)'])
            ->addColumn('pay_date', 'date', ['null' => false, 'comment' => 'Friday immediately after period_end'])
            ->addColumn('status', 'enum', ['values' => ['Open', 'AttendanceClosed', 'Disbursed', 'Closed'], 'null' => false, 'default' => 'Open'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['period_start'], ['unique' => true, 'name' => 'uq_period_start'])
            ->addIndex(['pay_date'], ['unique' => true, 'name' => 'uq_period_pay_date'])
            ->create();

        // Period integrity checks per ADR-0002 §16
        $this->execute("ALTER TABLE payroll_period ADD CONSTRAINT chk_period_start_friday "
            . "CHECK (DAYOFWEEK(period_start) = 6)"); // 6=Friday in MySQL
        $this->execute("ALTER TABLE payroll_period ADD CONSTRAINT chk_period_end_thursday "
            . "CHECK (DAYOFWEEK(period_end) = 5)");   // 5=Thursday
        $this->execute("ALTER TABLE payroll_period ADD CONSTRAINT chk_period_seven_days "
            . "CHECK (DATEDIFF(period_end, period_start) = 6)");
        $this->execute("ALTER TABLE payroll_period ADD CONSTRAINT chk_period_pay_date "
            . "CHECK (pay_date = DATE_ADD(period_end, INTERVAL 1 DAY))");

        // -------------------------------------------------------------------
        // payroll_run — one per (period × branch); sole payroll state authority
        // -------------------------------------------------------------------
        $this->table('payroll_run', [
            'id'          => false,
            'primary_key' => ['payroll_run_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Branch-scoped payroll run; status is the sole approval state per ADR-0002',
        ])
            ->addColumn('payroll_run_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('payroll_period_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('branch_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('payroll_policy_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('status', 'enum', [
                'values'  => ['Draft', 'Computed', 'PendingOwnerApproval', 'Approved', 'Returned'],
                'null'    => false,
                'default' => 'Draft',
            ])
            ->addColumn('gross_pay', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('total_deductions', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('net_pay', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('computed_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('computed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('submitted_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('submitted_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('reviewed_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('reviewed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('return_reason', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('lock_version', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['payroll_period_id', 'branch_id'], ['unique' => true, 'name' => 'uq_run_period_branch'])
            ->addIndex(['payroll_run_id', 'payroll_period_id'], ['unique' => true, 'name' => 'uq_run_id_period'])
            ->addForeignKey('payroll_period_id', 'payroll_period', 'payroll_period_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('branch_id', 'branch', 'branch_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('payroll_policy_id', 'payroll_policy_version', 'policy_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('computed_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('submitted_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('reviewed_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE payroll_run ADD CONSTRAINT chk_run_returned_reason "
            . "CHECK (status != 'Returned' OR return_reason IS NOT NULL)");
        // NOTE: chk_run_approved_meta (reviewed_by IS NOT NULL when Approved/Returned) is enforced
        // at the application layer in PayrollService — MySQL 8 prohibits CHECK constraints that
        // reference a column also used in a FK referential action (error 3823).

        // -------------------------------------------------------------------
        // payroll — per-employee payroll detail; no duplicated approval status
        // -------------------------------------------------------------------
        $this->table('payroll', [
            'id'          => false,
            'primary_key' => ['payroll_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Employee payroll detail; approved rows are immutable per ADR-0002',
        ])
            ->addColumn('payroll_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('payroll_run_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('payroll_period_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('branch_assignment_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('salary_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('daily_rate_snapshot', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false, 'comment' => 'Immutable snapshot at computation time'])
            ->addColumn('gross_pay', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('total_deductions', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('net_pay', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['payroll_period_id', 'employee_id'], ['unique' => true, 'name' => 'uq_payroll_period_employee'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('branch_assignment_id', 'employee_branch_assignment', 'branch_assignment_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('salary_id', 'salary', 'salary_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('payroll_period_id', 'payroll_period', 'payroll_period_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // Composite FK ensuring the payroll's period matches its run's period
        $this->execute("ALTER TABLE payroll ADD CONSTRAINT fk_payroll_run_period "
            . "FOREIGN KEY (payroll_run_id, payroll_period_id) REFERENCES payroll_run(payroll_run_id, payroll_period_id) "
            . "ON DELETE RESTRICT ON UPDATE CASCADE");

        // -------------------------------------------------------------------
        // payroll_earnings — itemized earning lines; immutable after run approval
        // -------------------------------------------------------------------
        $this->table('payroll_earnings', [
            'id'          => false,
            'primary_key' => ['earning_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Itemized earning components with calculation evidence (JSON)',
        ])
            ->addColumn('earning_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('payroll_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('earning_type', 'enum', [
                'values' => ['Basic', 'Overtime', 'RegularHoliday', 'SpecialHoliday', 'ThirteenthMonth'],
                'null'   => false,
            ])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('source_attendance_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('source_request_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('quantity', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => false])
            ->addColumn('unit_rate', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => false])
            ->addColumn('multiplier', 'decimal', ['precision' => 8, 'scale' => 4, 'null' => false, 'default' => '1.0000'])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('calculation_details', 'text', ['null' => false, 'comment' => 'JSON snapshot of all inputs used to derive amount'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('payroll_id', 'payroll', 'payroll_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('source_attendance_id', 'attendance', 'attendance_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('source_request_id', 'request', 'request_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // deduction — itemized deduction lines; immutable after run approval
        // -------------------------------------------------------------------
        $this->table('deduction', [
            'id'          => false,
            'primary_key' => ['deduction_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Itemized deduction lines including government contribution employee shares',
        ])
            ->addColumn('deduction_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('payroll_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('deduction_type', 'enum', [
                'values' => ['Late', 'Undertime', 'CashAdvance', 'SSS', 'PhilHealth', 'PagIBIG', 'IncomeTax'],
                'null'   => false,
            ])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('source_attendance_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('quantity', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => false])
            ->addColumn('unit_rate', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => false])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('calculation_details', 'text', ['null' => false, 'comment' => 'JSON snapshot'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('payroll_id', 'payroll', 'payroll_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('source_attendance_id', 'attendance', 'attendance_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // contribution_record — links government contribution to deduction line
        // -------------------------------------------------------------------
        $this->table('contribution_record', [
            'id'          => false,
            'primary_key' => ['contribution_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Government contribution with employer share; unique per payroll×program',
        ])
            ->addColumn('contribution_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('payroll_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('deduction_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('contribution_policy_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('contribution_type', 'enum', ['values' => ['SSS', 'PhilHealth', 'PagIBIG'], 'null' => false])
            ->addColumn('eemr_basis', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false, 'comment' => '(daily_rate × 313) ÷ 12'])
            ->addColumn('employee_share', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('employer_share', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('deduction_date', 'date', ['null' => false, 'comment' => 'pay_date of the last Friday in the month'])
            ->addColumn('calculation_details', 'text', ['null' => false, 'comment' => 'JSON snapshot'])
            ->addColumn('status', 'enum', ['values' => ['Computed', 'Locked'], 'null' => false, 'default' => 'Computed'])
            ->addColumn('locked_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('locked_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['payroll_id', 'contribution_type'], ['unique' => true, 'name' => 'uq_cr_payroll_type'])
            ->addIndex(['deduction_id'], ['unique' => true, 'name' => 'uq_cr_deduction_id'])
            ->addForeignKey('payroll_id', 'payroll', 'payroll_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('deduction_id', 'deduction', 'deduction_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('contribution_policy_id', 'contribution_policy_version', 'contribution_policy_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('locked_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // payslip — one per payroll detail; unique
        // -------------------------------------------------------------------
        $this->table('payslip', [
            'id'          => false,
            'primary_key' => ['payslip_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Digital payslip generated after payroll approval; one per payroll row',
        ])
            ->addColumn('payslip_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('payroll_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('issue_date', 'date', ['null' => false])
            ->addColumn('file_path', 'string', ['limit' => 500, 'null' => true, 'default' => null, 'comment' => 'Path in storage/private/payslips/'])
            ->addColumn('generated_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('generated_at', 'datetime', ['null' => false])
            ->addColumn('content_hash', 'char', ['limit' => 64, 'null' => true, 'default' => null, 'comment' => 'SHA-256 of the generated file'])
            ->addIndex(['payroll_id'], ['unique' => true, 'name' => 'uq_payslip_payroll_id'])
            ->addForeignKey('payroll_id', 'payroll', 'payroll_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('generated_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // cash_advance_repayment (deferred from migration 004; needs payroll)
        // -------------------------------------------------------------------
        $this->table('cash_advance_repayment', [
            'id'          => false,
            'primary_key' => ['repayment_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Weekly repayment deductions linked to payroll and deduction rows',
        ])
            ->addColumn('repayment_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('history_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('payroll_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('deduction_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['history_id', 'payroll_id'], ['unique' => true, 'name' => 'uq_car_history_payroll'])
            ->addIndex(['deduction_id'], ['unique' => true, 'name' => 'uq_car_deduction_id'])
            ->addForeignKey('history_id', 'cash_advance_history', 'history_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('payroll_id', 'payroll', 'payroll_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('deduction_id', 'deduction', 'deduction_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE cash_advance_repayment ADD CONSTRAINT chk_car_amount "
            . "CHECK (amount > 0)");
    }

    public function down(): void
    {
        $tables = [
            'cash_advance_repayment',
            'payslip',
            'contribution_record',
            'deduction',
            'payroll_earnings',
            'payroll',
            'payroll_run',
            'payroll_period',
            'pagibig_rate',
            'philhealth_rate',
            'sss_bracket',
            'contribution_policy_version',
            'payroll_policy_version',
            'salary',
        ];

        foreach ($tables as $t) {
            if ($this->hasTable($t)) {
                $this->table($t)->drop()->save();
            }
        }
    }
}
