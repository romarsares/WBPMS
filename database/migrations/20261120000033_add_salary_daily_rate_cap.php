<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Records the introduction of the approved maximum daily-rate policy.
 *
 * The policy is enforced by SalaryPolicy at every application write path.
 * A database CHECK cannot be added safely because salary preserves immutable
 * historical rates that may predate this policy and exceed the new maximum.
 */
final class AddSalaryDailyRateCap extends AbstractMigration
{
    public function up(): void
    {
        // Intentionally no schema change; see class documentation.
    }

    public function down(): void
    {
        // No schema change to reverse.
    }
}
