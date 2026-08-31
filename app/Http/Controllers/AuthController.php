<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Application\AuthService;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Session\DatabaseSessionHandler;

/**
 * AuthController — handles login form display, login form submission, and logout.
 *
 * HTTP:
 *   GET  /login  → showLogin()  — render login form
 *   POST /login  → login()      — process credentials
 *   POST /logout → logout()     — destroy session
 *
 * REQ001: login with username and password
 * ADR-0001: MySQL-backed sessions, CSRF protection, role-based redirect
 */
final class AuthController
{
    /**
     * GET /login — render the login form.
     *
     * @param array<string, string> $params
     */
    public function showLogin(array $params = []): void
    {
        // If already authenticated, redirect to the appropriate dashboard
        $this->startSessionIfNeeded();
        $identity = AuthMiddleware::identity();

        if ($identity !== null) {
            $this->redirectToDashboard($identity['role_name']);

            return;
        }

        // Pick up login errors AND session-expiry flash messages set by
        // AuthMiddleware::requireRoles() on expired/unauthenticated redirects.
        $error = $_SESSION['_login_error']
              ?? $_SESSION['_flash_error']
              ?? null;
        unset($_SESSION['_login_error'], $_SESSION['_flash_error']);

        $csrfField = CsrfMiddleware::field();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        require APP_ROOT . '/resources/views/auth/login.php';
    }

    /**
     * POST /login — validate credentials and create an authenticated session.
     *
     * @param array<string, string> $params
     */
    public function login(array $params = []): void
    {
        $this->startSessionIfNeeded();

        // CSRF was already verified by the Router before this method is called.

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        // Basic input presence check — defer full validation to AuthService
        if ($username === '' || $password === '') {
            $_SESSION['_login_error'] = 'Username and password are required.';
            $this->redirect('/login');

            return;
        }

        $connection = $this->makeConnection();
        $service    = new AuthService($connection);

        $identity = $service->attemptLogin(
            $username,
            $password,
            $this->requestId(),
            $this->clientIp(),
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        if ($identity === null) {
            // Generic error — do not reveal whether username or password was wrong
            $_SESSION['_login_error'] = 'Invalid username or password.';
            $this->redirect('/login');

            return;
        }

        // Persist authenticated identity; regenerates session ID internally
        AuthMiddleware::setIdentity($identity);

        $this->redirectToDashboard($identity['role_name']);
    }

    /**
     * POST /logout — destroy the server session and redirect to login.
     *
     * @param array<string, string> $params
     */
    public function logout(array $params = []): void
    {
        $this->startSessionIfNeeded();
        AuthMiddleware::destroySession();
        $this->redirect('/login');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function startSessionIfNeeded(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            AuthMiddleware::startSession();
        }
    }

    private function makeConnection(): Connection
    {
        // Load the database config for the current environment
        $config = require APP_ROOT . '/config/database.php';

        return new Connection($config);
    }

    private function redirectToDashboard(string $roleName): void
    {
        $path = match ($roleName) {
            'HRHead'        => '/hr/dashboard',
            'BusinessOwner' => '/owner/dashboard',
            'Employee'      => '/employee/dashboard',
            default         => '/login',
        };

        $this->redirect($path);
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }

    private function requestId(): string
    {
        return (string) ($_SERVER['HTTP_X_REQUEST_ID']
            ?? sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff), random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
            ));
    }

    private function clientIp(): string
    {
        return (string) ($_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '');
    }
}
