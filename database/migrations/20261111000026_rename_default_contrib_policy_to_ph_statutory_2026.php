<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Rename the legacy 'Default' contribution policy code to 'PH-STATUTORY-2026'.
 *
 * The production database was seeded before the policy naming convention was
 * finalised. The original seeder inserted policy_code = 'Default' / version = 'V1'.
 * The current seeder (Contribution2026Seeder) inserts 'CONTRIB-2026' / 'V1', but
 * only if no row already exists, so the old 'Default' row was never renamed.
 *
 * This migration renames every occurrence of policy_code = 'Default' to
 * 'PH-STATUTORY-2026' so the Benefits & Deductions screen shows the correct
 * label instead of "Default V1".
 *
 * Safe to run multiple times: the WHERE clause is a no-op when no 'Default' row exists.
 */
final class RenameDefaultContribPolicyToPhStatutory2026 extends AbstractMigration
{
    public function up(): void
    {
        // Rename the policy code displayed in Benefits & Deductions
        $this->execute(
            "UPDATE contribution_policy_version
                SET policy_code = 'PH-STATUTORY-2026',
                    updated_at  = NOW()
              WHERE policy_code = 'Default'"
        );
    }

    public function down(): void
    {
        $this->execute(
            "UPDATE contribution_policy_version
                SET policy_code = 'Default',
                    updated_at  = NOW()
              WHERE policy_code = 'PH-STATUTORY-2026'"
        );
    }
}
