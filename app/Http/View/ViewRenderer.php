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
        $notifCount = self::notificationCount($roleName, isset($identity['employee_id']) ? (int) $identity['employee_id'] : null);

        // Active sidebar nav key — forwarded from the view's $data array when
        // provided. If omitted (most controllers), derive it from the request
        // path so the sidebar always highlights the current module without
        // requiring every controller to pass 'activePage' explicitly.
        $activePage = isset($data['activePage']) ? (string) $data['activePage'] : self::deriveActivePage();

        // One-time flash messages stored in the session.
        //
        // Two storage formats exist in the codebase:
        //   A) ViewRenderer::flash()      → $_SESSION['_flash'] = 'string message'
        //   B) ViewRenderer::flashError() → $_SESSION['_flash_error'] = 'string message'
        //   C) Old-style controllers      → $_SESSION['_flash'][] = ['success'|'error', 'msg']
        //
        // Normalise everything into a single list<[type, message]> for layout.php.
        $flashMessages = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $rawFlash   = $_SESSION['_flash']         ?? null;
            $rawError   = $_SESSION['_flash_error']   ?? null;
            $rawWarning = $_SESSION['_flash_warning'] ?? null;
            $rawInfo    = $_SESSION['_flash_info']    ?? null;
            unset(
                $_SESSION['_flash'],
                $_SESSION['_flash_error'],
                $_SESSION['_flash_warning'],
                $_SESSION['_flash_info']
            );

            if (is_string($rawFlash) && $rawFlash !== '') {
                $flashMessages[] = ['success', $rawFlash];
            } elseif (is_array($rawFlash)) {
                foreach ($rawFlash as $item) {
                    if (is_array($item) && count($item) === 2) {
                        $flashMessages[] = [$item[0], $item[1]];
                    }
                }
            }

            if (is_string($rawError) && $rawError !== '') {
                $flashMessages[] = ['error', $rawError];
            }
            if (is_string($rawWarning) && $rawWarning !== '') {
                $flashMessages[] = ['warning', $rawWarning];
            }
            if (is_string($rawInfo) && $rawInfo !== '') {
                $flashMessages[] = ['info', $rawInfo];
            }
        }

        // Auto-toast validation errors passed via $data['errors'].
        //
        // Controllers re-render the form with an $errors array on validation
        // failure. The view already shows these inline (near the fields), but
        // the client also wants a toast so the feedback is impossible to miss.
        //
        // Rules:
        //   - Only fire when $data['errors'] is a non-empty array or non-empty string.
        //   - Always show the generic message "Please correct the highlighted fields."
        //     as the toast — detailed messages stay beside their fields inline.
        //   - Do NOT fire if a flashError is already queued (avoid duplication).
        $hasFlashError = !empty(array_filter($flashMessages, static fn($m) => $m[0] === 'error'));
        if (!$hasFlashError && !empty($data['errors'])) {
            $errorsRaw = $data['errors'];
            $hasErrors = false;

            if (is_string($errorsRaw) && $errorsRaw !== '') {
                $hasErrors = true;
            } elseif (is_array($errorsRaw)) {
                foreach ($errorsRaw as $msg) {
                    if (is_string($msg) && $msg !== '') {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            if ($hasErrors) {
                $flashMessages[] = ['error', 'Please correct the highlighted fields.'];
            }
        }

        // Keep backward-compatible $flash/$flashError string variables for
        // layout.php — pass null so the layout's own ?? guard does not shadow them.
        $flash      = null;
        $flashError = null;

        // CSRF token for the layout's logout form
        $csrf = (PHP_SAPI !== 'cli') ? CsrfMiddleware::token() : '';

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        // Render the layout
        self::captureLayout(
            $title, $content, $roleName, $userName, $csrf,
            $flash, $flashError, $activePage, $notifCount, $flashMessages
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

    /**
     * Store a one-time warning flash message in the session.
     */
    public static function flashWarning(string $message): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash_warning'] = $message;
        }
    }

    /**
     * Store a one-time info flash message in the session.
     */
    public static function flashInfo(string $message): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash_info'] = $message;
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
     *
     * @param list<array{0:string,1:string}> $flashMessages Normalised [type, message] pairs
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
        array   $flashMessages = [],
    ): void {
        $layoutPath = APP_ROOT . '/resources/views/layout.php';

        if (!is_file($layoutPath)) {
            // Fallback: emit the content directly without layout
            echo $content;
            return;
        }

        require $layoutPath;
    }

    /**
     * Derive the active sidebar nav key from the current request URI.
     *
     * Maps URL path prefixes to the nav key used in layout.php's $nav arrays.
     * Controllers may still override this by passing 'activePage' explicitly.
     */
    private static function deriveActivePage(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        // Strip query string
        $path = strtok($uri, '?') ?: '';
        // Normalise to lower-case, remove trailing slash
        $path = strtolower(rtrim($path, '/'));

        // Strip the app base path prefix so we match just the route segment
        $basePath = strtolower(rtrim(
            parse_url((string) ($_ENV['APP_BASE_URL'] ?? ''), PHP_URL_PATH) ?? '',
            '/'
        ));
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        $path = ltrim($path, '/');

        // Map path prefixes → nav key (most-specific first)
        $map = [
            'hr/attendance'    => 'attendance',
            'hr/employees'     => 'employees',
            'hr/schedules'     => 'schedule',
            'hr/requests'      => 'requests',
            'hr/payroll'       => 'payroll',
            'hr/salary'        => 'salary',
            'hr/benefits'      => 'benefits',
            'hr/reports'       => 'reports',
            'hr/settings'      => 'settings',
            'hr/dashboard'     => 'dashboard',
            'owner/dashboard'  => 'dashboard',
            'owner/payroll'    => 'payroll',
            'owner/requests'   => 'requests',
            'employee/dashboard'  => 'dashboard',
            'employee/attendance' => 'my-attendance',
            'employee/requests'   => 'my-requests',
            'employee/payslips'   => 'my-payslips',
            'users'            => 'users',
        ];

        foreach ($map as $prefix => $key) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $key;
            }
        }

        return '';
    }

    /** Return the current in-app alert count appropriate for the signed-in role. */
    private static function notificationCount(string $roleName, ?int $employeeId = null): int
    {
        if (!in_array($roleName, ['BusinessOwner', 'Employee'], true)) {
            return 0;
        }

        try {
            $pdo = (new Connection(require APP_ROOT . '/config/database.php'))->pdo();
            if ($roleName === 'BusinessOwner') {
                return (int) $pdo->query(
                    "SELECT COUNT(*) FROM payroll_run WHERE status = 'PendingOwnerApproval'"
                )->fetchColumn();
            }
            if ($employeeId === null || $employeeId < 1) {
                return 0;
            }
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM employee_hr_notice WHERE employee_id = :employee_id AND acknowledged_at IS NULL'
            );
            $stmt->execute([':employee_id' => $employeeId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            // A notification must never prevent the page from rendering.
            return 0;
        }
    }
}
