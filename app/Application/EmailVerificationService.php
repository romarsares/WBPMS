<?php

declare(strict_types=1);

namespace Wbpms\Application;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Mail\Mailer;

/**
 * EmailVerificationService — OTP-based email ownership verification.
 *
 * Two fully independent use-cases with separate DB columns:
 *
 * USE-CASE A — Forgot-password recovery (pre-login)
 *   Columns: email_verification_code, email_verification_expires_at,
 *            email_verification_attempts
 *
 *   1. initiateVerification($email)   — generate & email OTP
 *   2. verifyCode($email, $code)      — validate; return user_id or null
 *   3. clearVerification($userId)     — wipe columns after token issued
 *   4. remainingAttempts($email)      — hint for the UI
 *
 * USE-CASE B — First-login email confirmation (post password-change, authenticated)
 *   Columns: email_confirm_code, email_confirm_expires_at,
 *            email_confirm_attempts
 *
 *   1. initiateEmailConfirmation($userId)       — generate & email OTP
 *   2. verifyConfirmationCode($userId, $code)   — validate; return bool
 *   3. markEmailVerified($userId)               — set is_email_verified=1, wipe columns
 *   4. remainingConfirmationAttempts($userId)   — hint for the UI
 *
 * Keeping the two column sets separate means a forgot-password request can
 * never clobber a pending email-confirmation OTP, and vice versa.
 *
 * Security properties (both flows):
 *   - Raw OTP is never stored; only SHA-256(otp) is persisted.
 *   - Brute-force is capped at MAX_ATTEMPTS (5) per issued code.
 *   - New code supersedes any prior challenge (attempt counter reset).
 *   - 15-minute expiry enforced at the DB query level.
 *   - Account-existence is not disclosed on unknown email submissions.
 */
final class EmailVerificationService
{
    /** OTP lifetime in seconds (15 minutes). */
    private const CODE_TTL = 900;

    /** Maximum failed verification attempts before the OTP is locked. */
    private const MAX_ATTEMPTS = 5;

    private Connection $connection;
    private Mailer $mailer;

    public function __construct(Connection $connection, Mailer $mailer)
    {
        $this->connection = $connection;
        $this->mailer     = $mailer;
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Issue a 6-digit OTP to the given email address.
     *
     * Always returns true even when the address is unregistered, to prevent
     * account-existence enumeration. The caller must show a generic success
     * message regardless of return value.
     *
     * @param  string $email  The email address submitted by the user
     * @return bool           true if the OTP was sent; false if unknown/inactive
     * @throws \RuntimeException on mail delivery failure (smtp driver only)
     */
    public function initiateVerification(string $email): bool
    {
        $pdo = $this->connection->pdo();

        // Only Active accounts can recover their password.
        $stmt = $pdo->prepare(
            "SELECT user_id, username, account_email
               FROM users
              WHERE account_email = :email
                AND status = 'Active'
              LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return false;
        }

        // Generate a 6-digit numeric OTP.
        $rawCode  = $this->generateCode();
        $codeHash = hash('sha256', $rawCode);
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . self::CODE_TTL . ' seconds')
            ->format('Y-m-d H:i:s');

        // Supersede any prior challenge: reset attempts and store the new hash.
        $upd = $pdo->prepare(
            "UPDATE users
                SET email_verification_code        = :hash,
                    email_verification_expires_at  = :expires,
                    email_verification_attempts    = 0,
                    updated_at                     = UTC_TIMESTAMP()
              WHERE user_id = :id"
        );
        $upd->execute([
            ':hash'    => $codeHash,
            ':expires' => $expiresAt,
            ':id'      => $user['user_id'],
        ]);

        // Send the OTP.
        try {
            $this->mailer->send(
                to:      (string) $user['account_email'],
                subject: 'WBPMS — Email Verification Code',
                body:    $this->buildEmailBody((string) $user['username'], $rawCode),
            );
        } catch (\RuntimeException $e) {
            error_log(
                '[EmailVerificationService] Mail delivery failed for user_id='
                . $user['user_id'] . ': ' . $e->getMessage()
            );
            throw $e;
        }

        return true;
    }

    /**
     * Verify a submitted OTP against the stored hash for the given email.
     *
     * Increments the attempt counter on failure. Locks the OTP after
     * MAX_ATTEMPTS failed tries (the user must request a new code).
     *
     * @param  string   $email    The email address the user typed on the forgot-password form
     * @param  string   $rawCode  The 6-digit code submitted by the user
     * @return int|null           user_id on success; null if invalid, expired, or locked
     */
    public function verifyCode(string $email, string $rawCode): ?int
    {
        if ($rawCode === '' || !ctype_digit($rawCode) || strlen($rawCode) !== 6) {
            return null;
        }

        $pdo  = $this->connection->pdo();
        $now  = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $hash = hash('sha256', $rawCode);

        // Fetch the pending challenge for this email.
        $stmt = $pdo->prepare(
            "SELECT user_id,
                    email_verification_code,
                    email_verification_expires_at,
                    email_verification_attempts
               FROM users
              WHERE account_email = :email
                AND status = 'Active'
                AND email_verification_code IS NOT NULL
              LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            // No active challenge for this email.
            return null;
        }

        // Reject expired challenges.
        if ($row['email_verification_expires_at'] === null
            || $row['email_verification_expires_at'] <= $now
        ) {
            return null;
        }

        // Reject locked challenges (too many failed attempts).
        if ((int) $row['email_verification_attempts'] >= self::MAX_ATTEMPTS) {
            return null;
        }

        // Constant-time comparison to resist timing attacks.
        if (!hash_equals((string) $row['email_verification_code'], $hash)) {
            // Increment the failed-attempt counter.
            $pdo->prepare(
                "UPDATE users
                    SET email_verification_attempts = email_verification_attempts + 1,
                        updated_at                  = UTC_TIMESTAMP()
                  WHERE user_id = :id"
            )->execute([':id' => $row['user_id']]);

            return null;
        }

        // Success — clear the OTP immediately so it cannot be reused.
        $this->clearVerification((int) $row['user_id']);

        return (int) $row['user_id'];
    }

    /**
     * Clear all OTP columns for a user.
     *
     * Called after successful verification transitions (e.g., once a
     * password-reset token has been issued).
     */
    public function clearVerification(int $userId): void
    {
        $this->connection->pdo()->prepare(
            "UPDATE users
                SET email_verification_code       = NULL,
                    email_verification_expires_at = NULL,
                    email_verification_attempts   = 0,
                    updated_at                    = UTC_TIMESTAMP()
              WHERE user_id = :id"
        )->execute([':id' => $userId]);
    }

    // -----------------------------------------------------------------------
    // USE-CASE B — First-login email confirmation (authenticated employee)
    // -----------------------------------------------------------------------

    /**
     * Issue a 6-digit OTP to confirm an authenticated employee's email address.
     *
     * Writes to the dedicated email_confirm_* columns, completely independent
     * of the forgot-password email_verification_* columns.
     *
     * @param  int $userId  The authenticated user's user_id
     * @throws \RuntimeException on mail delivery failure (smtp driver only)
     * @throws \RuntimeException when the user is not found or has no email
     */
    public function initiateEmailConfirmation(int $userId): void
    {
        $pdo = $this->connection->pdo();

        $stmt = $pdo->prepare(
            "SELECT user_id, username, account_email
               FROM users
              WHERE user_id = :id
                AND status = 'Active'
              LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            throw new \RuntimeException('User not found or account is not active.');
        }

        $email = (string) $user['account_email'];
        if ($email === '') {
            throw new \RuntimeException('No email address is registered for this account.');
        }

        $rawCode   = $this->generateCode();
        $codeHash  = hash('sha256', $rawCode);
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . self::CODE_TTL . ' seconds')
            ->format('Y-m-d H:i:s');

        $pdo->prepare(
            "UPDATE users
                SET email_confirm_code       = :hash,
                    email_confirm_expires_at = :expires,
                    email_confirm_attempts   = 0,
                    updated_at               = UTC_TIMESTAMP()
              WHERE user_id = :id"
        )->execute([
            ':hash'    => $codeHash,
            ':expires' => $expiresAt,
            ':id'      => $userId,
        ]);

        try {
            $this->mailer->send(
                to:      $email,
                subject: 'WBPMS — Confirm Your Email Address',
                body:    $this->buildConfirmationEmailBody((string) $user['username'], $rawCode),
            );
        } catch (\RuntimeException $e) {
            error_log(
                '[EmailVerificationService] Email confirmation delivery failed for user_id='
                . $userId . ': ' . $e->getMessage()
            );
            throw $e;
        }
    }

    /**
     * Verify a submitted OTP for the first-login email confirmation flow.
     *
     * Reads from email_confirm_* columns only — completely isolated from the
     * forgot-password columns.
     *
     * @param  int    $userId   The authenticated user's user_id
     * @param  string $rawCode  The 6-digit code submitted by the employee
     * @return bool             true on success; false if invalid, expired, or locked
     */
    public function verifyConfirmationCode(int $userId, string $rawCode): bool
    {
        if ($rawCode === '' || !ctype_digit($rawCode) || strlen($rawCode) !== 6) {
            return false;
        }

        $pdo  = $this->connection->pdo();
        $now  = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $hash = hash('sha256', $rawCode);

        $stmt = $pdo->prepare(
            "SELECT user_id,
                    email_confirm_code,
                    email_confirm_expires_at,
                    email_confirm_attempts
               FROM users
              WHERE user_id = :id
                AND status = 'Active'
                AND email_confirm_code IS NOT NULL
              LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return false;
        }

        if ($row['email_confirm_expires_at'] === null
            || $row['email_confirm_expires_at'] <= $now
        ) {
            return false;
        }

        if ((int) $row['email_confirm_attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (!hash_equals((string) $row['email_confirm_code'], $hash)) {
            $pdo->prepare(
                "UPDATE users
                    SET email_confirm_attempts = email_confirm_attempts + 1,
                        updated_at             = UTC_TIMESTAMP()
                  WHERE user_id = :id"
            )->execute([':id' => $userId]);

            return false;
        }

        // Success — clear immediately so it cannot be reused.
        $this->clearConfirmation($userId);

        return true;
    }

    /**
     * Mark a user's email address as verified and clear the confirmation OTP.
     *
     * Setting is_email_verified = 1 permanently disables the OTP gate for
     * this account on all future logins.
     */
    public function markEmailVerified(int $userId): void
    {
        $this->connection->pdo()->prepare(
            "UPDATE users
                SET is_email_verified        = 1,
                    email_confirm_code       = NULL,
                    email_confirm_expires_at = NULL,
                    email_confirm_attempts   = 0,
                    updated_at               = UTC_TIMESTAMP()
              WHERE user_id = :id"
        )->execute([':id' => $userId]);
    }

    /**
     * Return remaining allowed attempts for a pending email confirmation OTP.
     * Returns null when no active (non-expired) challenge exists for the user.
     */
    public function remainingConfirmationAttempts(int $userId): ?int
    {
        $now  = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $stmt = $this->connection->pdo()->prepare(
            "SELECT email_confirm_attempts
               FROM users
              WHERE user_id = :id
                AND status = 'Active'
                AND email_confirm_code IS NOT NULL
                AND email_confirm_expires_at > :now
              LIMIT 1"
        );
        $stmt->execute([':id' => $userId, ':now' => $now]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return max(0, self::MAX_ATTEMPTS - (int) $row['email_confirm_attempts']);
    }

    /**
     * Clear the email confirmation OTP columns for a user (without marking verified).
     * Used internally after successful verification.
     */
    private function clearConfirmation(int $userId): void
    {
        $this->connection->pdo()->prepare(
            "UPDATE users
                SET email_confirm_code       = NULL,
                    email_confirm_expires_at = NULL,
                    email_confirm_attempts   = 0,
                    updated_at               = UTC_TIMESTAMP()
              WHERE user_id = :id"
        )->execute([':id' => $userId]);
    }

    /**
     * Return the remaining number of allowed attempts for a pending challenge.
     * Returns null if no active (non-expired) challenge exists for the email.
     */
    public function remainingAttempts(string $email): ?int
    {
        $now  = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $stmt = $this->connection->pdo()->prepare(
            "SELECT email_verification_attempts
               FROM users
              WHERE account_email = :email
                AND status = 'Active'
                AND email_verification_code IS NOT NULL
                AND email_verification_expires_at > :now
              LIMIT 1"
        );
        $stmt->execute([':email' => $email, ':now' => $now]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return max(0, self::MAX_ATTEMPTS - (int) $row['email_verification_attempts']);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Generate a 6-digit numeric OTP using a cryptographically secure source.
     */
    private function generateCode(): string
    {
        // random_int is cryptographically secure on all supported PHP versions.
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function buildEmailBody(string $username, string $code): string
    {
        $ttlMinutes = self::CODE_TTL / 60;

        return <<<TEXT
        Hello {$username},

        You are receiving this email because a password reset was requested for your
        WBPMS account.

        Your email verification code is:

            {$code}

        Enter this code on the verification page within {$ttlMinutes} minutes.

        For your security:
          - This code can only be used once.
          - After {$ttlMinutes} minutes it will expire and you will need to request a new one.
          - If you did not request a password reset, you can safely ignore this email.

        — Light Diamond Enterprises WBPMS
        TEXT;
    }

    private function buildConfirmationEmailBody(string $username, string $code): string
    {
        $ttlMinutes = self::CODE_TTL / 60;

        return <<<TEXT
        Hello {$username},

        Your WBPMS account password has been set successfully.

        To complete your account setup, please confirm your email address by entering
        the following 6-digit verification code:

            {$code}

        Enter this code on the verification screen within {$ttlMinutes} minutes.

        For your security:
          - This code can only be used once.
          - After {$ttlMinutes} minutes it will expire. You can request a new code from
            the verification screen.
          - Once verified, you will not be asked for a code again.
          - If you did not perform this action, please contact your HR administrator
            immediately.

        — Light Diamond Enterprises WBPMS
        TEXT;
    }
}
