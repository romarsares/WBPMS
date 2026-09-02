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

    /**
     * GET /forgot — show the forgot-password form.
     *
     * @param array<string, string> $params
     */
    public function showForgot(array $params = []): void
    {
        $this->startSessionIfNeeded();
        $csrfField = CsrfMiddleware::field();
        $error     = $_SESSION['_forgot_error'] ?? null;
        unset($_SESSION['_forgot_error']);

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/auth/forgot.php';
    }

    /**
     * POST /forgot — validate email and create an OTP challenge.
     *
     * REQ002: password recovery via email verification.
     * In development/demo mode the OTP is shown on screen.
     * In production it would be sent by email (hook point: sendOtpEmail()).
     *
     * @param array<string, string> $params
     */
    public function sendOtp(array $params = []): void
    {
        $this->startSessionIfNeeded();

        $email = trim(strtolower((string) ($_POST['account_email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['_forgot_error'] = 'Please enter a valid email address.';
            $this->redirect('/forgot');
            return;
        }

        $pdo  = $this->makeConnection()->pdo();
        $stmt = $pdo->prepare(
            "SELECT user_id, username FROM users WHERE account_email = :email AND status = 'Active' LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Always show the same message to avoid user enumeration
        if (!$user) {
            $_SESSION['_otp_email_sent'] = $email;
            $this->redirect('/reset-otp');
            return;
        }

        $userId = (int) $user['user_id'];

        // Expire any existing challenges for this user
        $pdo->prepare(
            "UPDATE password_reset_challenge
                SET consumed_at = UTC_TIMESTAMP()
              WHERE user_id = :uid AND consumed_at IS NULL"
        )->execute([':uid' => $userId]);

        // Generate 6-digit OTP
        $otp    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash   = password_hash($otp, PASSWORD_DEFAULT);
        $expiry = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+15 minutes')
            ->format('Y-m-d H:i:s');
        $now    = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $pdo->prepare(
            "INSERT INTO password_reset_challenge
                (user_id, otp_hash, expires_at, attempts_remaining, created_at)
             VALUES
                (:uid, :hash, :exp, 5, :now)"
        )->execute([':uid' => $userId, ':hash' => $hash, ':exp' => $expiry, ':now' => $now]);

        // In production: send $otp to $email via mailer
        // In dev/demo: store in session for display
        $appEnv = $_ENV['APP_ENV'] ?? 'production';
        if ($appEnv === 'development') {
            $_SESSION['_dev_otp'] = $otp;
        }

        $_SESSION['_otp_email_sent'] = $email;
        $_SESSION['_otp_user_id']    = $userId;
        $this->redirect('/reset-otp');
    }

    /**
     * GET /reset-otp — show OTP entry form.
     *
     * @param array<string, string> $params
     */
    public function showOtpForm(array $params = []): void
    {
        $this->startSessionIfNeeded();
        if (empty($_SESSION['_otp_email_sent'])) {
            $this->redirect('/forgot');
            return;
        }

        $csrfField = CsrfMiddleware::field();
        $error     = $_SESSION['_otp_error'] ?? null;
        $devOtp    = $_SESSION['_dev_otp']   ?? null;
        unset($_SESSION['_otp_error']);

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/auth/otp.php';
    }

    /**
     * POST /reset-otp — verify OTP.
     *
     * @param array<string, string> $params
     */
    public function verifyOtp(array $params = []): void
    {
        $this->startSessionIfNeeded();
        if (empty($_SESSION['_otp_user_id'])) {
            $this->redirect('/forgot');
            return;
        }

        $otp    = trim((string) ($_POST['otp'] ?? ''));
        $userId = (int) $_SESSION['_otp_user_id'];
        $now    = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $pdo    = $this->makeConnection()->pdo();

        $stmt = $pdo->prepare(
            "SELECT challenge_id, otp_hash, attempts_remaining
               FROM password_reset_challenge
              WHERE user_id = :uid
                AND expires_at > :now
                AND consumed_at IS NULL
              ORDER BY created_at DESC
              LIMIT 1"
        );
        $stmt->execute([':uid' => $userId, ':now' => $now]);
        $challenge = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$challenge) {
            $_SESSION['_otp_error'] = 'OTP has expired. Please request a new one.';
            $this->redirect('/reset-otp');
            return;
        }

        if ((int) $challenge['attempts_remaining'] <= 0) {
            $_SESSION['_otp_error'] = 'Too many failed attempts. Please request a new OTP.';
            $this->redirect('/forgot');
            return;
        }

        if (!password_verify($otp, (string) $challenge['otp_hash'])) {
            $pdo->prepare(
                "UPDATE password_reset_challenge
                    SET attempts_remaining = attempts_remaining - 1
                  WHERE challenge_id = :id"
            )->execute([':id' => $challenge['challenge_id']]);
            $_SESSION['_otp_error'] = 'Incorrect OTP. Please try again.';
            $this->redirect('/reset-otp');
            return;
        }

        // Mark challenge as consumed and store a reset token in session
        $pdo->prepare(
            "UPDATE password_reset_challenge
                SET consumed_at = :now
              WHERE challenge_id = :id"
        )->execute([':now' => $now, ':id' => $challenge['challenge_id']]);

        $_SESSION['_reset_user_id'] = $userId;
        unset($_SESSION['_dev_otp'], $_SESSION['_otp_user_id'], $_SESSION['_otp_email_sent']);
        $this->redirect('/reset-password');
    }

    /**
     * GET /reset-password — show new password form.
     *
     * @param array<string, string> $params
     */
    public function showResetForm(array $params = []): void
    {
        $this->startSessionIfNeeded();
        if (empty($_SESSION['_reset_user_id'])) {
            $this->redirect('/forgot');
            return;
        }

        $csrfField = CsrfMiddleware::field();
        $error     = $_SESSION['_reset_error'] ?? null;
        unset($_SESSION['_reset_error']);

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/auth/reset_password.php';
    }

    /**
     * POST /reset-password — persist new password. REQ002.
     *
     * @param array<string, string> $params
     */
    public function resetPassword(array $params = []): void
    {
        $this->startSessionIfNeeded();
        if (empty($_SESSION['_reset_user_id'])) {
            $this->redirect('/forgot');
            return;
        }

        $userId   = (int) $_SESSION['_reset_user_id'];
        $password = (string) ($_POST['password']         ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $_SESSION['_reset_error'] = 'Password must be at least 8 characters.';
            $this->redirect('/reset-password');
            return;
        }
        if ($password !== $confirm) {
            $_SESSION['_reset_error'] = 'Passwords do not match.';
            $this->redirect('/reset-password');
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->makeConnection()->pdo()->prepare(
            "UPDATE users SET password_hash = :hash, updated_at = UTC_TIMESTAMP() WHERE user_id = :id"
        )->execute([':hash' => $hash, ':id' => $userId]);

        unset($_SESSION['_reset_user_id']);
        $_SESSION['_login_error'] = 'Password reset successfully. Please log in with your new password.';
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
