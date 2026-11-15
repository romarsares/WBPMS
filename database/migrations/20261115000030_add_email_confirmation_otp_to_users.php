<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 030: Add dedicated email-confirmation OTP columns to users.
 *
 * The forgot-password recovery flow already uses:
 *   email_verification_code
 *   email_verification_expires_at
 *   email_verification_attempts
 *
 * Sharing those columns with the first-login email-confirmation flow caused
 * a collision: triggering forgot-password after a password change overwrote
 * the confirmation OTP, making it appear expired on the verify-email screen.
 *
 * These three new columns are dedicated to the email-confirmation flow so
 * the two flows are completely independent.
 */
final class AddEmailConfirmationOtpToUsers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('email_confirm_code', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
                'after'   => 'is_email_verified',
                'comment' => 'SHA-256 hex of the 6-digit OTP for first-login email confirmation; NULL when inactive',
            ])
            ->addColumn('email_confirm_expires_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'after'   => 'email_confirm_code',
                'comment' => 'UTC expiry for email_confirm_code; NULL means no active challenge',
            ])
            ->addColumn('email_confirm_attempts', 'integer', [
                'signed'  => false,
                'null'    => false,
                'default' => 0,
                'after'   => 'email_confirm_expires_at',
                'comment' => 'Failed attempt counter for email confirmation OTP; resets when a new code is issued',
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('users')
            ->removeColumn('email_confirm_attempts')
            ->removeColumn('email_confirm_expires_at')
            ->removeColumn('email_confirm_code')
            ->save();
    }
}
