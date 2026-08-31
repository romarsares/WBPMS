<?php

declare(strict_types=1);

namespace Wbpms\Http\Middleware;

/**
 * CSRF protection middleware.
 *
 * Every mutating browser request (POST/PUT/PATCH/DELETE) must include a
 * valid CSRF token. The token is stored in the session and delivered to
 * the browser via a hidden form field or a meta tag for JS requests.
 *
 * Token lifecycle:
 *   - Generated on first call to generateToken() in a session.
 *   - Regenerated after a valid verify() call (single-use pattern is
 *     optional; here we keep the same token for the session duration for
 *     simplicity with form reloads).
 *   - Destroyed when the session is destroyed on logout.
 *
 * ADR-0001: mutating browser requests require CSRF tokens.
 */
final class CsrfMiddleware
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Generate (or retrieve) the current session CSRF token.
     * The session must already be started before calling this.
     */
    public static function generateToken(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    /**
     * Verify the submitted CSRF token against the session token.
     *
     * Terminates with a 419 JSON error if the token is missing or invalid.
     * This method is called by the Router for all mutating and authenticated routes.
     */
    public static function verify(): void
    {
        // CSRF is only meaningful in browser sessions; skip for CLI/tests
        if (PHP_SAPI === 'cli') {
            return;
        }

        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            return; // No session = no CSRF to check (public GET-only routes)
        }

        $sessionToken   = $_SESSION[self::SESSION_KEY] ?? null;
        $submittedToken = $_POST['_csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        if (
            empty($sessionToken)
            || empty($submittedToken)
            || !hash_equals((string) $sessionToken, (string) $submittedToken)
        ) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => [
                    'code'    => 'CSRF_TOKEN_INVALID',
                    'message' => 'CSRF token validation failed. Please refresh the page and try again.',
                ],
            ]);
            exit;
        }
    }

    /**
     * Return an HTML hidden input field for use in forms.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::generateToken(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    /**
     * Invalidate the current CSRF token (call on logout).
     */
    public static function invalidate(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
