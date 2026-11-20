<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Store the time an employee was hired separately from the effective date.
 *
 * The field is nullable so existing employee records remain valid until HR
 * supplies a time through the edit form.
 */
final class AddHireTimeToEmployee extends AbstractMigration
{
    public function up(): void
    {
        $this->table('employee')
            ->addColumn('hire_time', 'time', [
                'null'    => true,
                'default' => null,
                'after'   => 'hire_date',
                'comment' => 'Local time the employee was hired',
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('employee')
            ->removeColumn('hire_time')
            ->save();
    }
}
