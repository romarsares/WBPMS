<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 007: Job Position reference table.
 *
 * Adds a `job_position` lookup table that HR can manage through the
 * Settings → Positions screen. The `employee.position` column is
 * kept as a VARCHAR snapshot (for historical payroll immutability) and
 * also holds a FK to job_position so the employee form can offer a
 * managed dropdown while still allowing free-text overrides if needed.
 *
 * ADR-0002: parent delete → RESTRICT; HR archives rather than hard-deletes.
 */
final class CreateJobPositionTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('job_position', [
            'id'          => false,
            'primary_key' => ['position_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Managed list of job titles/positions for the employee form dropdown',
        ])
            ->addColumn('position_id', 'biginteger', [
                'signed'   => false,
                'identity' => true,
                'null'     => false,
            ])
            ->addColumn('position_title', 'string', [
                'limit'   => 100,
                'null'    => false,
                'comment' => 'Display name shown in the dropdown and stored on the employee record',
            ])
            ->addColumn('department', 'string', [
                'limit'   => 100,
                'null'    => true,
                'default' => null,
                'comment' => 'Optional grouping label (e.g. Warehouse, Admin)',
            ])
            ->addColumn('status', 'enum', [
                'values'  => ['Active', 'Inactive'],
                'null'    => false,
                'default' => 'Active',
            ])
            ->addColumn('sort_order', 'smallinteger', [
                'signed'  => false,
                'null'    => false,
                'default' => 0,
                'comment' => 'Controls display order in dropdown; lower number = higher up',
            ])
            ->addColumn('created_at', 'datetime', [
                'null'    => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('updated_at', 'datetime', [
                'null'    => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update'  => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['position_title'], ['unique' => true, 'name' => 'uq_position_title'])
            ->addIndex(['status', 'sort_order'], ['name' => 'idx_position_status_sort'])
            ->create();
    }

    public function down(): void
    {
        $this->table('job_position')->drop()->save();
    }
}
