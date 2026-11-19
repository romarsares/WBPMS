<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 028: Add email-verification columns to users table.
 *
 * Adds two nullable columns that back the forgot-password OTP step:
 *
 *   email_verification_code       — SHA-256 hex digest of the 6-digit OTP sent
 *                                   to the user's registered email address.
 *                                   The raw code is never stored.
 *   email_verification_expires_at — UTC datetime after which the OTP is invalid.
 *                                   15-minute expiry enforced in EmailVerificationService.
 *
 * Panel revision (Phase 2): verify that the submitted email address exists and
 * belongs to the account owner before issuing a password-reset token.
 */
final class AddEmailVerificationToUsers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('email_verification_code', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
                'after'   => 'password_reset_expires_at',
                'comment' => 'SHA-256 hex of the 6-digit OTP emailed to the user; NULL when inactive',
            ])
            ->addColumn('email_verification_expires_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'after'   => 'email_verification_code',
                'comment' => 'UTC expiry; NULL means no active verification challenge',
            ])
            ->addColumn('email_verification_attempts', 'integer', [
                'signed'  => false,
                'null'    => false,
                'default' => 0,
                'after'   => 'email_verification_expires_at',
                'comment' => 'Failed OTP attempt counter; resets when a new code is issued',
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('users')
            ->removeColumn('email_verification_attempts')
            ->removeColumn('email_verification_expires_at')
            ->removeColumn('email_verification_code')
            ->save();
    }
}
