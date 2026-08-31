<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 006: Bank Details and Disbursement tables.
 *
 * Creates: bank_details, disbursement_batch, deposit_slip
 *
 * ADR-0001: one aggregate BDO cheque + individual deposit slips per employee.
 *   REQ073: printable preparation list only; no bank upload API.
 * ADR-0002 §14: one disbursement_batch per payroll_period;
 *   batch created only after all included branch runs are Approved.
 * Canonical schema v1.1: Bank disbursement section.
 */
final class CreateBankDisbursementTables extends AbstractMigration
{
    public function up(): void
    {
        // -------------------------------------------------------------------
        // bank_details — effective-dated employee bank accounts
        // -------------------------------------------------------------------
        $this->table('bank_details', [
            'id'          => false,
            'primary_key' => ['bank_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Effective-dated bank accounts; one active BDO account per employee',
        ])
            ->addColumn('bank_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('bank_name', 'string', ['limit' => 100, 'null' => false, 'default' => 'BDO'])
            ->addColumn('account_name', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('account_number', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('account_type', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Inactive'], 'null' => false, 'default' => 'Active'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['bank_name', 'account_number', 'effective_from'], ['unique' => true, 'name' => 'uq_bank_name_acct_from'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE bank_details ADD CONSTRAINT chk_bank_period "
            . "CHECK (effective_to IS NULL OR effective_to > effective_from)");

        // -------------------------------------------------------------------
        // disbursement_batch — one per payroll_period; one cheque total
        // -------------------------------------------------------------------
        $this->table('disbursement_batch', [
            'id'          => false,
            'primary_key' => ['batch_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'One BDO cheque per payroll period; created after all branch runs are Approved',
        ])
            ->addColumn('batch_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('payroll_period_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('bank_name', 'string', ['limit' => 100, 'null' => false, 'default' => 'BDO'])
            ->addColumn('cheque_number', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('cheque_total', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['Prepared', 'Issued', 'Reconciled'], 'null' => false, 'default' => 'Prepared'])
            ->addColumn('prepared_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('submitted_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['payroll_period_id'], ['unique' => true, 'name' => 'uq_disbursement_period'])
            ->addIndex(['cheque_number'], ['unique' => true, 'name' => 'uq_disbursement_cheque'])
            ->addForeignKey('payroll_period_id', 'payroll_period', 'payroll_period_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('prepared_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // deposit_slip — one per employee payroll within a disbursement batch
        // -------------------------------------------------------------------
        $this->table('deposit_slip', [
            'id'          => false,
            'primary_key' => ['slip_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Individual employee deposit slips for the printable BDO preparation list',
        ])
            ->addColumn('slip_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('batch_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('payroll_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('bank_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['Pending', 'Prepared', 'Deposited'], 'null' => false, 'default' => 'Pending'])
            ->addColumn('prepared_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['payroll_id'], ['unique' => true, 'name' => 'uq_slip_payroll_id'])
            ->addForeignKey('batch_id', 'disbursement_batch', 'batch_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('payroll_id', 'payroll', 'payroll_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('bank_id', 'bank_details', 'bank_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE deposit_slip ADD CONSTRAINT chk_slip_amount "
            . "CHECK (amount > 0)");
    }

    public function down(): void
    {
        $tables = ['deposit_slip', 'disbursement_batch', 'bank_details'];

        foreach ($tables as $t) {
            if ($this->hasTable($t)) {
                $this->table($t)->drop()->save();
            }
        }
    }
}
