<?php

declare(strict_types=1);

namespace Wbpms\Http\View;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

/**
 * ViewRenderer — renders a view file into the shared layout.
 *
 * Usage:
 *   ViewRenderer::render('hr/dashboard', ['totalEmployees' => 42]);
 *
 * The view receives all $data keys as local variables.
 * The layout always receives: $title, $content, $roleName, $userName, $csrf,
 * $flash, $flashError.
 *
 * Convention: view paths are relative to resources/views/ and must not include
 * the .php extension. Layout is always resources/views/layout.php.
 */
final class ViewRenderer
{
    /**
     * Render a view file into the layout and emit the full HTML page.
     *
     * @param  string               $view  Relative view path, e.g. 'hr/dashboard'
     * @param  array<string, mixed> $data  Variables to pass into the view
     * @param  string               $title Page title (shown in <title> and nav)
     */
    public static function render(string $view, array $data = [], string $title = 'WBPMS'): void
    {
        // Capture the view's output
        $content = self::captureView($view, $data);

        // Resolve layout-level variables from the session identity
        $identity = (PHP_SAPI !== 'cli') ? AuthMiddleware::identity() : null;
        $roleName = $identity['role_name'] ?? '';
        $userName = $identity['username']  ?? '';
        $notifCount = self::notificationCount($roleName);

        // Active sidebar nav key — forwarded from the view's $data array when provided.
        $activePage = isset($data['activePage']) ? (string) $data['activePage'] : '';

        // One-time flash messages stored in the session
        $flash      = null;
        $flashError = null;
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $flash      = isset($_SESSION['_flash'])       ? (string) $_SESSION['_flash']       : null;
            $flashError = isset($_SESSION['_flash_error']) ? (string) $_SESSION['_flash_error'] : null;
            unset($_SESSION['_flash'], $_SESSION['_flash_error']);
        }

        // CSRF token for the layout's logout form
        $csrf = (PHP_SAPI !== 'cli') ? CsrfMiddleware::token() : '';

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        // Render the layout
        self::captureLayout(
            $title, $content, $roleName, $userName, $csrf,
            $flash, $flashError, $activePage, $notifCount
        );
    }

    /**
     * Store a one-time success flash message in the session.
     */
    public static function flash(string $message): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash'] = $message;
        }
    }

    /**
     * Store a one-time error flash message in the session.
     */
    public static function flashError(string $message): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash_error'] = $message;
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Render a view file and return its HTML output as a string.
     *
     * @param  string               $view Relative path, no .php extension
     * @param  array<string, mixed> $data Variables to extract into the view scope
     */
    private static function captureView(string $view, array $data): string
    {
        $viewPath = APP_ROOT . '/resources/views/' . $view . '.php';

        if (!is_file($viewPath)) {
            throw new \RuntimeException("View not found: {$viewPath}");
        }

        // Inject the CSRF token so views with inline forms can use $csrf directly
        if (!isset($data['csrf']) && PHP_SAPI !== 'cli') {
            $data['csrf'] = CsrfMiddleware::token();
        }

        // Inject $base (APP_BASE_URL without trailing slash) so every view can
        // build correct href/action paths regardless of subdirectory deployment.
        if (!isset($data['base'])) {
            $data['base'] = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        }

        // Extract data into local scope for the view file
        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        return (string) ob_get_clean();
    }

    /**
     * Output the layout with the captured $content injected.
     */
    private static function captureLayout(
        string  $title,
        string  $content,
        string  $roleName,
        string  $userName,
        string  $csrf,
        ?string $flash,
        ?string $flashError,
        string  $activePage = '',
        int     $notifCount = 0,
    ): void {
        $layoutPath = APP_ROOT . '/resources/views/layout.php';

        if (!is_file($layoutPath)) {
            // Fallback: emit the content directly without layout
            echo $content;
            return;
        }

        require $layoutPath;
    }

    /** Return the current in-app alert count appropriate for the signed-in role. */
    private static function notificationCount(string $roleName): int
    {
        if ($roleName !== 'BusinessOwner') {
            return 0;
        }

        try {
            $pdo = (new Connection(require APP_ROOT . '/config/database.php'))->pdo();
            return (int) $pdo->query(
                "SELECT COUNT(*) FROM payroll_run WHERE status = 'PendingOwnerApproval'"
            )->fetchColumn();
        } catch (\Throwable) {
            // A notification must never prevent the page from rendering.
            return 0;
        }
    }
}
