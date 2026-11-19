<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 032: Add contractual_allowed to job_position.
 *
 * HR can mark certain positions as not available to Contractual employees
 * (e.g. Business Owner, HR Head).  When the flag is false the Add/Edit
 * Employee form rejects the combination at the server level.
 *
 * Default is true (1) so all existing positions remain valid for both
 * employee types without any data patch.
 */
final class AddContractualAllowedToJobPosition extends AbstractMigration
{
    public function up(): void
    {
        $this->table('job_position')
            ->addColumn('contractual_allowed', 'boolean', [
                'null'    => false,
                'default' => true,
                'after'   => 'default_daily_rate',
                'comment' => 'When false, Contractual employees cannot be assigned this position',
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('job_position')
            ->removeColumn('contractual_allowed')
            ->save();
    }
}
