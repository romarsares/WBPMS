<?php

declare(strict_types=1);

namespace Wbpms\Http\Middleware;

use Wbpms\Application\AuthService;
use Wbpms\Infrastructure\Database\Connection;
/**
 * Authentication and RBAC middleware.
 *
 * Enforces the role matrix defined in design.md.
 * The Router calls requireRoles() before dispatching any protected route.
 *
 * Session keys:
 *   _auth.user_id                  int
 *   _auth.username                 string
 *   _auth.role_name                string  (BusinessOwner | HRHead | Employee)
 *   _auth.employee_id              int|null
 *   _auth.requires_password_change bool    (true for auto-provisioned accounts on first login)
 *   _auth.logged_in_at             int    Unix timestamp (for absolute expiry check)
 *   _auth.last_active              int    Unix timestamp (for idle expiry check)
 *
 * REQN007: role-based access control on every protected route.
 * ADR-0001: 30-minute idle + 12-hour absolute session expiry.
 */
final class AuthMiddleware
{
    private const SESSION_KEY   = '_auth';
    private const IDLE_TTL      = 1800;   // 30 minutes
    private const ABSOLUTE_TTL  = 43200;  // 12 hours

    /**
     * Start (or resume) the PHP session with secure cookie attributes.
     * Call this once in the front controller before dispatching.
     */
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

        session_set_cookie_params([
            'lifetime' => 0, // Session cookie (browser-close expiry)
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isProduction,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('wbpms_session');
        session_start();
    }

    /**
     * Store an authenticated identity in the session after successful login.
     *
     * Regenerates the session ID to prevent fixation attacks.
     *
     * @param array{user_id: int, username: string, role_name: string, employee_id: int|null, requires_password_change: bool} $identity
     */
    public static function setIdentity(array $identity): void
    {
        session_regenerate_id(true);

        $now = time();
        $_SESSION[self::SESSION_KEY] = [
            'user_id'                  => $identity['user_id'],
            'username'                 => $identity['username'],
            'role_name'                => $identity['role_name'],
            'employee_id'              => $identity['employee_id'],
            'requires_password_change' => (bool) ($identity['requires_password_change'] ?? false),
            'logged_in_at'             => $now,
            'last_active'              => $now,
        ];
    }

    /**
     * Return the current session identity, or null if not authenticated / expired.
     *
     * Updates last_active on every call for idle-expiry tracking.
     *
     * @return array{user_id: int, username: string, role_name: string, employee_id: int|null, requires_password_change: bool}|null
     */
    public static function identity(): ?array
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }

        $auth = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($auth) || empty($auth['user_id'])) {
            return null;
        }

        $now = time();

        // Idle expiry check
        if (($now - (int) $auth['last_active']) > self::IDLE_TTL) {
            self::destroySession();

            return null;
        }

        // Absolute expiry check
        if (($now - (int) $auth['logged_in_at']) > self::ABSOLUTE_TTL) {
            self::destroySession();

            return null;
        }

        // Account status is authoritative even when a browser still has a
        // valid session cookie. This immediately revokes access after HR
        // archives the linked employee account.
        try {
            $isActive = (new AuthService(new Connection(require APP_ROOT . '/config/database.php')))
                ->isUserActive((int) $auth['user_id']);
        } catch (\Throwable) {
            // Fail closed if account state cannot be checked.
            $isActive = false;
        }
        if (!$isActive) {
            self::destroySession();

            return null;
        }

        // Refresh last_active
        $_SESSION[self::SESSION_KEY]['last_active'] = $now;

        return [
            'user_id'                  => (int)    $auth['user_id'],
            'username'                 => (string) $auth['username'],
            'role_name'                => (string) $auth['role_name'],
            'employee_id'              => $auth['employee_id'] !== null ? (int) $auth['employee_id'] : null,
            'requires_password_change' => (bool)   ($auth['requires_password_change'] ?? false),
        ];
    }

    /**
     * Enforce that the current request has an authenticated session with one
     * of the allowed roles.
     *
     * - If the identity is missing or expired (including 30-minute idle timeout
     *   and 12-hour absolute timeout), browser requests are redirected to /login
     *   with a one-time flash so the user sees a friendly message. AJAX/API
     *   requests (Accept: application/json) receive a 401 JSON envelope.
     * - If the identity exists but the role is not permitted, a 403 is returned.
     *   Browser users see a 403 redirect to /login (role mismatch is abnormal
     *   for a correctly built UI, so a plain redirect is acceptable).
     *
     * @param list<string> $allowedRoles Role names from the role table
     */
    public static function requireRoles(array $allowedRoles): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $identity = self::identity();

        if ($identity === null) {
            // Determine whether this is a browser or an API/AJAX request.
            $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
            $isApiRequest = str_contains($acceptHeader, 'application/json')
                && !str_contains($acceptHeader, 'text/html');

            if ($isApiRequest) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'error' => [
                        'code'    => 'UNAUTHENTICATED',
                        'message' => 'Authentication required. Please sign in.',
                    ],
                ]);
                exit;
            }

            // Browser request: store a flash message and redirect to login.
            // The session may already be destroyed; start a fresh one to
            // carry the flash message across the redirect.
            if (session_status() === PHP_SESSION_NONE) {
                self::startSession();
            }
            $_SESSION['_flash_error'] = 'Your session has expired. Please sign in again.';

            $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
            header('Location: ' . $base . '/login', true, 302);
            exit;
        }

        if (!empty($allowedRoles) && !in_array($identity['role_name'], $allowedRoles, true)) {
            $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
            $isApiRequest = str_contains($acceptHeader, 'application/json')
                && !str_contains($acceptHeader, 'text/html');

            if (!$isApiRequest) {
                http_response_code(403);
                header('Content-Type: text/html; charset=utf-8');

                $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
                $roleName = $identity['role_name'];
                require APP_ROOT . '/resources/views/errors/403.php';
                exit;
            }

            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => [
                    'code'    => 'FORBIDDEN',
                    'message' => 'You do not have permission to perform this action.',
                ],
            ]);
            exit;
        }
    }

    /**
     * Destroy the server session.
     * Called by AuthController::logout() and on expiry.
     */
    public static function destroySession(): void
    {
        CsrfMiddleware::invalidate();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }
}
