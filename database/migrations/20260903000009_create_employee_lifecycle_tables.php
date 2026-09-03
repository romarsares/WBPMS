<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Records durable employee employment episodes and HR lifecycle decisions.
 *
 * Existing employee, attendance, payroll, and request rows remain untouched.
 * These tables only add the evidence needed to explain an archive or rehire.
 */
final class CreateEmployeeLifecycleTables extends AbstractMigration
{
    public function up(): void
    {
        $this->table('employee_employment_episode', [
            'id' => false,
            'primary_key' => ['episode_id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'One row per continuous employment period for an employee',
        ])
            ->addColumn('episode_id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('started_on', 'date', ['null' => false])
            ->addColumn('ended_on', 'date', ['null' => true, 'default' => null])
            ->addColumn('start_reason', 'enum', ['values' => ['Hire', 'Rehire'], 'null' => false])
            ->addColumn('end_reason', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('notes', 'string', ['limit' => 1000, 'null' => true, 'default' => null])
            ->addColumn('created_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'started_on'], ['unique' => true, 'name' => 'uq_episode_employee_start'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('created_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        $this->execute('ALTER TABLE employee_employment_episode ADD CONSTRAINT chk_episode_dates '
            . 'CHECK (ended_on IS NULL OR ended_on >= started_on)');

        $this->table('employee_lifecycle_event', [
            'id' => false,
            'primary_key' => ['event_id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Append-only audit trail for archive and rehire decisions',
        ])
            ->addColumn('event_id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('event_type', 'enum', ['values' => ['Archive', 'Rehire'], 'null' => false])
            ->addColumn('previous_status', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('new_status', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('effective_date', 'date', ['null' => false])
            ->addColumn('reason', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('notes', 'string', ['limit' => 1000, 'null' => true, 'default' => null])
            ->addColumn('acted_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'effective_date'], ['name' => 'idx_lifecycle_employee_date'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('acted_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('employee_lifecycle_event')->drop()->save();
        $this->table('employee_employment_episode')->drop()->save();
    }
}
