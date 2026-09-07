<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 20260907000014 — add sss_number to employee table.
 *
 * SSS number was omitted from the original employee schema. It is placed
 * alongside the existing government-identifier columns (philhealth_number,
 * pagibig_number, tin_number) and follows the same nullable VARCHAR(30)
 * convention.
 *
 * Up:   ADD COLUMN sss_number VARCHAR(30) NULL after tin_number
 * Down: DROP COLUMN sss_number
 */
final class AddSssNumberToEmployee extends AbstractMigration
{
    public function up(): void
    {
        $this->table('employee')
            ->addColumn('sss_number', 'string', [
                'limit'   => 30,
                'null'    => true,
                'default' => null,
                'after'   => 'tin_number',
                'comment' => 'Employee SSS ID number (optional)',
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('employee')
            ->removeColumn('sss_number')
            ->save();
    }
}
