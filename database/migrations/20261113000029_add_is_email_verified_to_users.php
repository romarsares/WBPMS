<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 029: Add is_email_verified flag to users table.
 *
 * Tracks whether an Employee account holder has confirmed ownership of their
 * registered email address via a one-time OTP sent after their first password
 * change.  Once set to 1 it is never reset by normal login activity; it is
 * only reset when HR changes the account's email address so the employee must
 * re-verify the new address.
 *
 * Back-fill: existing Employee accounts that have already completed their
 * first-login password change (requires_password_change = 0) are marked as
 * verified so they are not forced into the OTP screen on their next login.
 * Accounts still carrying a temporary password (requires_password_change = 1)
 * are left at 0 and will go through the full new flow when they first log in.
 *
 * HR Head and Business Owner accounts are set to 1 so the gate never fires
 * for them (the OTP step is Employee-only).
 */
final class AddIsEmailVerifiedToUsers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('is_email_verified', 'boolean', [
                'null'    => false,
                'default' => false,
                'after'   => 'email_verification_attempts',
                'comment' => '1 once the employee has confirmed their email via one-time OTP; reset to 0 when account_email changes',
            ])
            ->save();

        // Back-fill: employees who already changed their temporary password are
        // considered verified so they are not disrupted by the new gate.
        $this->execute(
            "UPDATE users u
               JOIN role r ON r.role_id = u.role_id
                SET u.is_email_verified = 1
              WHERE r.role_name = 'Employee'
                AND u.requires_password_change = 0
                AND u.status != 'Archived'"
        );

        // Non-employee roles never go through the OTP gate; mark them verified
        // so the middleware condition is a simple equality check.
        $this->execute(
            "UPDATE users u
               JOIN role r ON r.role_id = u.role_id
                SET u.is_email_verified = 1
              WHERE r.role_name != 'Employee'"
        );
    }

    public function down(): void
    {
        $this->table('users')
            ->removeColumn('is_email_verified')
            ->save();
    }
}
