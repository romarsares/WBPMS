<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 021: Add password-reset columns to users table.
 *
 * Adds two nullable columns:
 *   password_reset_token      — SHA-256 hex digest of the raw random token
 *                               sent in the reset link (never stored raw).
 *   password_reset_expires_at — UTC datetime after which the token is invalid.
 *                               One-hour expiry enforced in PasswordResetService.
 *
 * REQ002: forgot-password email-based reset-link flow.
 */
final class AddPasswordResetToUsers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('password_reset_token', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
                'after'   => 'password_hash',
                'comment' => 'SHA-256 hex of the raw token emailed to the user',
            ])
            ->addColumn('password_reset_expires_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'after'   => 'password_reset_token',
                'comment' => 'UTC expiry; NULL means no active reset request',
            ])
            ->addIndex(['password_reset_token'], [
                'name'   => 'idx_users_pwd_reset_token',
                'unique' => false,   // hashed; lookup is fast, uniqueness not critical
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('users')
            ->removeIndex('idx_users_pwd_reset_token')
            ->removeColumn('password_reset_expires_at')
            ->removeColumn('password_reset_token')
            ->save();
    }
}
