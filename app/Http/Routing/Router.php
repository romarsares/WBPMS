<?php

declare(strict_types=1);

namespace Wbpms\Http\Routing;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;

/**
 * Minimal explicit route dispatcher.
 *
 * Routes are registered as (method, path, handler, roles).
 * Dispatching resolves the matching route, runs CSRF and RBAC middleware,
 * then instantiates and calls the controller action.
 *
 * Supports {param} placeholder segments in paths.
 */
final class Router
{
    /**
     * @var list<array{
     *   method: string,
     *   path: string,
     *   handler: array{0: class-string, 1: string},
     *   roles: list<string>
     * }>
     */
    private array $routes = [];

    /**
     * Register a route.
     *
     * @param string                          $method  HTTP verb (GET, POST, PUT, PATCH, DELETE)
     * @param string                          $path    URI path, leading slash required; {param} segments supported
     * @param array{0: class-string, 1: string} $handler [ControllerClass::class, 'actionMethod']
     * @param list<string>                    $roles   Allowed role_name values; empty = public
     */
    public function add(
        string $method,
        string $path,
        array $handler,
        array $roles = []
    ): void {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $path,
            'handler' => $handler,
            'roles'   => $roles,
        ];
    }

    /**
     * Match the current HTTP request and dispatch to the correct controller.
     *
     * Emits a 404 JSON error envelope when no route matches.
     */
    public function handle(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Support _method override for HTML forms (PUT/PATCH/DELETE via POST).
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path       = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        // Strip the subdirectory base so routes registered as '/health' match
        // whether the app runs at document root or under e.g. /wbpms/public/.
        // SCRIPT_NAME is e.g. '/wbpms/public/index.php'; base is '/wbpms/public'.
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $base       = rtrim(dirname($scriptName), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if ($path === '' || $path === false) {
            $path = '/';
        }

        // Normalize trailing slash (keep root as-is).
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            [$matched, $params] = $this->match(
                $route['method'],
                $route['path'],
                $method,
                $path
            );

            if (!$matched) {
                continue;
            }


            // CSRF: verify token only for mutating requests (POST, PUT, PATCH, DELETE).
            // GET requests to protected routes do NOT require a CSRF token;
            // authentication is enforced separately by requireRoles() below.
=======
            // CSRF: verify token only for state-mutating requests.

            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                CsrfMiddleware::verify();
            }

            // RBAC: require an authenticated session with an allowed role.
            if (!empty($route['roles'])) {
                AuthMiddleware::requireRoles($route['roles']);
            }

            // Instantiate controller and invoke action.
            [$controllerClass, $action] = $route['handler'];
            $controller = new $controllerClass();
            $controller->{$action}($params);

            return;
        }

        // 404 — no matching route.
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => [
                'code'      => 'NOT_FOUND',
                'message'   => 'The requested resource was not found.',
                'requestId' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
            ],
        ]);
    }

    /**
     * Match a route definition against the current method and path.
     *
     * Converts {param} placeholders to named capture groups.
     *
     * @return array{0: bool, 1: array<string, string>}
     */
    private function match(
        string $routeMethod,
        string $routePath,
        string $requestMethod,
        string $requestPath
    ): array {
        if ($routeMethod !== $requestMethod) {
            return [false, []];
        }

        // Convert {param} to named regex capture groups.
        $pattern = preg_replace(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            '(?P<$1>[^/]+)',
            $routePath
        );
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $requestPath, $matches) !== 1) {
            return [false, []];
        }

        // Keep only named string keys (exclude integer-indexed captures).
        $params = array_filter(
            $matches,
            static fn ($k): bool => is_string($k),
            ARRAY_FILTER_USE_KEY
        );

        return [true, $params];
    }
}
