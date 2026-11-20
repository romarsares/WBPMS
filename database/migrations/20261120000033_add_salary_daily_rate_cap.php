<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Enforces the approved maximum daily rate for every salary write path. */
final class AddSalaryDailyRateCap extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'ALTER TABLE salary ADD CONSTRAINT chk_salary_rate_max CHECK (daily_rate <= 800.00)'
        );
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE salary DROP CHECK chk_salary_rate_max');
    }
}
