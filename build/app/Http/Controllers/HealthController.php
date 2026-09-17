<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

/**
 * Health check endpoint.
 *
 * GET /health — returns a JSON 200 with application status.
 * Used to verify the server is running and configuration is loaded.
 */
final class HealthController
{
    /**
     * @param array<string, string> $params Route parameters (unused for this route)
     */
    public function index(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);

        echo json_encode([
            'data' => [
                'status'  => 'ok',
                'env'     => $_ENV['APP_ENV'] ?? 'unknown',
                'version' => '1.0.0',
                'time'    => (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->format('c'),
            ],
            'meta' => [],
        ], JSON_THROW_ON_ERROR);
    }
}
