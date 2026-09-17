<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Employee-facing records issued by HR from an attendance policy flag.
 *
 * These notices document HR's decision; they never apply discipline or change
 * employment status automatically.
 */
final class CreateEmployeeHrNoticeTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('employee_hr_notice', [
            'id'          => false,
            'primary_key' => ['notice_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Employee notices issued by HR from attendance policy reviews',
        ])
            ->addColumn('notice_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('policy_flag_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('issued_by', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 160, 'null' => false])
            ->addColumn('body', 'text', ['null' => false])
            ->addColumn('issued_at', 'datetime', ['null' => false])
            ->addColumn('acknowledged_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'acknowledged_at', 'issued_at'], ['name' => 'idx_employee_hr_notice_inbox'])
            ->addIndex(['policy_flag_id'], ['unique' => true, 'name' => 'uq_employee_hr_notice_policy_flag'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('policy_flag_id', 'attendance_policy_flag', 'flag_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('issued_by', 'users', 'user_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
