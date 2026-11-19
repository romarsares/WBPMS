<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 031: Add default_daily_rate to job_position.
 *
 * Enables the Add/Rehire Employee form to pre-populate the Daily Rate field
 * when HR selects a position from the dropdown.  The rate is advisory — HR
 * can override it before saving, and the authoritative rate record lives in
 * the salary table.
 *
 * The column is nullable so existing positions are unaffected.
 */
final class AddDefaultDailyRateToJobPosition extends AbstractMigration
{
    public function up(): void
    {
        $this->table('job_position')
            ->addColumn('default_daily_rate', 'decimal', [
                'precision' => 12,
                'scale'     => 2,
                'null'      => true,
                'default'   => null,
                'after'     => 'department',
                'comment'   => 'Advisory default daily rate shown when this position is selected on the employee form; HR may override',
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('job_position')
            ->removeColumn('default_daily_rate')
            ->save();
    }
}
