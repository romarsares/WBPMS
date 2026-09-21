<?php

declare(strict_types=1);

namespace Wbpms\Application;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Mail\Mailer;

/**
 * PasswordResetService — orchestrates the forgot-password email-link flow.
 *
 * Flow:
 *   1. initiateReset($email)
 *      - Look up an Active user by account_email.
 *      - Generate a cryptographically random 32-byte token; store its
 *        SHA-256 hash in users.password_reset_token with a 1-hour expiry.
 *      - Delegate sending the reset-link email to the Mailer.
 *      - Returns true on success, false if the email is not found or the
 *        account is not Active (always returns a generic response to the
 *        caller — caller must not reveal whether the address exists).
 *
 *   2. validateToken($token)
 *      - Looks up the hash of $token; confirms expiry has not passed.
 *      - Returns the user_id on success, null on failure.
 *
 *   3. consumeToken($token, $newPassword)
 *      - Re-validates the token, hashes the new password, updates the row,
 *        clears the reset columns, and returns true on success.
 *
 * REQ002: forgot-password email-based reset-link flow.
 */
final class PasswordResetService
{
    /** Token lifetime in seconds (1 hour). */
    private const TOKEN_TTL = 3600;

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
     * Initiate a password-reset for the given email address.
     *
     * Always returns true to the caller even when the address is not found,
     * to avoid leaking account-existence information.
     *
     * @param  string $email      Submitted email address
     * @param  string $baseUrl    APP_BASE_URL (used to build the reset link)
     * @return bool               true if the reset email was sent; false if
     *                            the address is unknown/inactive (but caller
     *                            should show a success message either way)
     */
    public function initiateReset(string $email, string $baseUrl): bool
    {
        $pdo  = $this->connection->pdo();

        // 1. Fetch the user — only Active accounts can reset.
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

        // 2. Generate raw token + store its hash.
        $rawToken  = bin2hex(random_bytes(32));          // 64 hex chars
        $hash      = hash('sha256', $rawToken);          // 64 hex chars
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . self::TOKEN_TTL . ' seconds')
            ->format('Y-m-d H:i:s');

        $upd = $pdo->prepare(
            "UPDATE users
                SET password_reset_token      = :hash,
                    password_reset_expires_at = :expires,
                    updated_at                = UTC_TIMESTAMP()
              WHERE user_id = :id"
        );
        $upd->execute([
            ':hash'    => $hash,
            ':expires' => $expiresAt,
            ':id'      => $user['user_id'],
        ]);

        // 3. Build the reset URL and send the email.
        $resetUrl = rtrim($baseUrl, '/') . '/reset-password?token=' . urlencode($rawToken);

        $this->mailer->send(
            to:      (string) $user['account_email'],
            subject: 'WBPMS — Password Reset Request',
            body:    $this->buildEmailBody((string) $user['username'], $resetUrl),
        );

        return true;
    }

    /**
     * Validate a raw reset token.
     *
     * @param  string   $rawToken   The token from the URL query string
     * @return int|null             user_id on success, null if invalid/expired
     */
    public function validateToken(string $rawToken): ?int
    {
        if ($rawToken === '') {
            return null;
        }

        $hash = hash('sha256', $rawToken);
        $now  = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $stmt = $this->connection->pdo()->prepare(
            "SELECT user_id
               FROM users
              WHERE password_reset_token      = :hash
                AND password_reset_expires_at > :now
                AND status = 'Active'
              LIMIT 1"
        );
        $stmt->execute([':hash' => $hash, ':now' => $now]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? (int) $row['user_id'] : null;
    }

    /**
     * Consume a valid reset token and set the new password.
     *
     * Re-validates the token before writing. Clears both reset columns
     * and the requires_password_change flag (if set) after a successful update.
     *
     * @param  string $rawToken    The token from the form submission
     * @param  string $newPassword Plain-text new password (already validated by caller)
     * @return bool                true on success, false if token is invalid/expired
     */
    public function consumeToken(string $rawToken, string $newPassword): bool
    {
        $userId = $this->validateToken($rawToken);

        if ($userId === null) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $upd = $this->connection->pdo()->prepare(
            "UPDATE users
                SET password_hash             = :hash,
                    password_reset_token      = NULL,
                    password_reset_expires_at = NULL,
                    requires_password_change  = 0,
                    updated_at                = UTC_TIMESTAMP()
              WHERE user_id = :id"
        );
        $upd->execute([':hash' => $hash, ':id' => $userId]);

        return true;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function buildEmailBody(string $username, string $resetUrl): string
    {
        $ttlMinutes = self::TOKEN_TTL / 60;

        return <<<TEXT
        Hello {$username},

        You are receiving this email because a password reset was requested for your
        WBPMS account.

        Click the link below (or copy it into your browser) to set a new password.
        This link will expire in {$ttlMinutes} minutes.

            {$resetUrl}

        If you did not request a password reset, you can safely ignore this email.
        Your password will not change unless you click the link above.

        — Light Diamond Enterprises WBPMS
        TEXT;
    }
}
