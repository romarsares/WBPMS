<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 008: Add requires_password_change flag to users.
 *
 * When an employee account is auto-provisioned, the flag is set to 1
 * so the system forces a password change on first login.
 * Cleared to 0 once the employee successfully sets their own password.
 *
 * Requirement 13 — Automatic Employee User Account Provisioning.
 */
final class AddRequiresPasswordChangeToUsers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('requires_password_change', 'boolean', [
                'null'    => false,
                'default' => false,
                'after'   => 'status',
                'comment' => 'Force password change on next login (auto-provisioned accounts)',
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('users')
            ->removeColumn('requires_password_change')
            ->save();
    }
}
