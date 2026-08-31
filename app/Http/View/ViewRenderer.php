<?php

declare(strict_types=1);

namespace Wbpms\Http\View;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;

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
        self::captureLayout($title, $content, $roleName, $userName, $csrf, $flash, $flashError);
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
        ?string $flashError
    ): void {
        $layoutPath = APP_ROOT . '/resources/views/layout.php';

        if (!is_file($layoutPath)) {
            // Fallback: emit the content directly without layout
            echo $content;
            return;
        }

        require $layoutPath;
    }
}
