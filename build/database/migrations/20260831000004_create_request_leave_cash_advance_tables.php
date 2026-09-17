<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 004: Request, Leave, and Cash Advance tables.
 *
 * Creates: request_type, request, leave_request_detail,
 *          overtime_request_detail, cash_advance_request_detail,
 *          leave_entitlement, leave_ledger, cash_advance_history,
 *          cash_advance_repayment
 *
 * ADR-0002 §12/§13: cash advance creates one obligation; leave uses annual
 *   entitlement plus append-only ledger; request lifecycle is distinct from archival.
 * Canonical schema v1.1: Requests, leave, and cash advances section.
 */
final class CreateRequestLeaveCashAdvanceTables extends AbstractMigration
{
    public function up(): void
    {
        // -------------------------------------------------------------------
        // request_type
        // -------------------------------------------------------------------
        $this->table('request_type', [
            'id'          => false,
            'primary_key' => ['request_type_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Lookup for Leave, Overtime, CashAdvance request categories',
        ])
            ->addColumn('request_type_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('type_name', 'enum', ['values' => ['Leave', 'Overtime', 'CashAdvance'], 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Inactive'], 'null' => false, 'default' => 'Active'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['type_name'], ['unique' => true, 'name' => 'uq_request_type_name'])
            ->create();

        // -------------------------------------------------------------------
        // request — core request header; type-specific payload in detail tables
        // -------------------------------------------------------------------
        $this->table('request', [
            'id'          => false,
            'primary_key' => ['request_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Employee request header; exactly one detail row per request_type',
        ])
            ->addColumn('request_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('request_type_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('reason', 'string', ['limit' => 1000, 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['Pending', 'Approved', 'Rejected', 'Cancelled'], 'null' => false, 'default' => 'Pending'])
            ->addColumn('submitted_at', 'datetime', ['null' => false])
            ->addColumn('reviewed_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('reviewed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('review_notes', 'string', ['limit' => 1000, 'null' => true, 'default' => null])
            ->addColumn('archived_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('archived_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id'], ['name' => 'idx_request_employee'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('request_type_id', 'request_type', 'request_type_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('reviewed_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('archived_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // leave_request_detail — 1:1 with request where type = Leave
        // -------------------------------------------------------------------
        $this->table('leave_request_detail', [
            'id'          => false,
            'primary_key' => ['request_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('request_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('leave_type', 'enum', ['values' => ['Sick'], 'null' => false, 'comment' => 'Sick leave; 4 days per year per ADR-0001'])
            ->addColumn('start_date', 'date', ['null' => false])
            ->addColumn('end_date', 'date', ['null' => false])
            ->addColumn('days_requested', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => false])
            ->addForeignKey('request_id', 'request', 'request_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE leave_request_detail ADD CONSTRAINT chk_lrd_dates "
            . "CHECK (end_date >= start_date AND days_requested > 0)");

        // -------------------------------------------------------------------
        // overtime_request_detail — 1:1 with request where type = Overtime
        // -------------------------------------------------------------------
        $this->table('overtime_request_detail', [
            'id'          => false,
            'primary_key' => ['request_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('request_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('overtime_date', 'date', ['null' => false])
            ->addColumn('start_time', 'time', ['null' => false])
            ->addColumn('end_time', 'time', ['null' => false])
            ->addColumn('requested_minutes', 'smallinteger', ['signed' => false, 'null' => false])
            ->addForeignKey('request_id', 'request', 'request_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE overtime_request_detail ADD CONSTRAINT chk_ord_minutes "
            . "CHECK (requested_minutes > 0)");

        // -------------------------------------------------------------------
        // cash_advance_request_detail — 1:1 with request where type = CashAdvance
        // -------------------------------------------------------------------
        $this->table('cash_advance_request_detail', [
            'id'          => false,
            'primary_key' => ['request_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('request_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addForeignKey('request_id', 'request', 'request_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE cash_advance_request_detail ADD CONSTRAINT chk_card_amount "
            . "CHECK (amount > 0)");

        // -------------------------------------------------------------------
        // leave_entitlement — annual allocation per employee per leave_type
        // -------------------------------------------------------------------
        $this->table('leave_entitlement', [
            'id'          => false,
            'primary_key' => ['entitlement_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Annual sick leave allocation; 4 days default per ADR-0001',
        ])
            ->addColumn('entitlement_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('leave_type', 'enum', ['values' => ['Sick'], 'null' => false])
            ->addColumn('leave_year', 'smallinteger', ['signed' => false, 'null' => false])
            ->addColumn('entitled_days', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => false, 'default' => '4.00'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'leave_type', 'leave_year'], ['unique' => true, 'name' => 'uq_le_employee_type_year'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // leave_ledger — append-only; balance derived by summing deltas
        // -------------------------------------------------------------------
        $this->table('leave_ledger', [
            'id'          => false,
            'primary_key' => ['entry_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Append-only ledger; running balance = SUM(days_delta) per entitlement',
        ])
            ->addColumn('entry_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('entitlement_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('request_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('entry_type', 'enum', ['values' => ['Grant', 'Usage', 'Adjustment', 'Reversal'], 'null' => false])
            ->addColumn('days_delta', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => false, 'comment' => 'Negative for Usage; positive for Grant/Reversal'])
            ->addColumn('recorded_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('notes', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['request_id'], ['unique' => true, 'name' => 'uq_ll_request_id'])
            ->addForeignKey('entitlement_id', 'leave_entitlement', 'entitlement_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('request_id', 'request', 'request_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('recorded_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // cash_advance_history — one obligation per approved cash advance request
        // -------------------------------------------------------------------
        $this->table('cash_advance_history', [
            'id'          => false,
            'primary_key' => ['history_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'One active obligation per approved cash advance request',
        ])
            ->addColumn('history_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('request_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('original_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('remaining_balance', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Paid', 'Cancelled'], 'null' => false, 'default' => 'Active'])
            ->addColumn('approved_at', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['request_id'], ['unique' => true, 'name' => 'uq_cah_request_id'])
            ->addForeignKey('request_id', 'request', 'request_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE cash_advance_history ADD CONSTRAINT chk_cah_balance "
            . "CHECK (remaining_balance >= 0 AND remaining_balance <= original_amount AND original_amount > 0)");

        // cash_advance_repayment deferred to migration 005 (needs payroll table)
    }

    public function down(): void
    {
        $tables = [
            'leave_ledger',
            'leave_entitlement',
            'cash_advance_history',
            'cash_advance_request_detail',
            'overtime_request_detail',
            'leave_request_detail',
            'request',
            'request_type',
        ];

        foreach ($tables as $t) {
            if ($this->hasTable($t)) {
                $this->table($t)->drop()->save();
            }
        }
    }
}
