<?php

declare(strict_types=1);

return [
    'env'      => $_ENV['APP_ENV']      ?? 'production',
    'base_url' => $_ENV['APP_BASE_URL'] ?? '',
    'key'      => $_ENV['APP_KEY']      ?? '',
    'timezone' => 'Asia/Manila',
    'locale'   => 'en',
    'session'  => [
        // 30-minute idle expiry (ADR-0001 / ADR-0003)
        'idle_ttl'     => 1800,
        // 12-hour absolute expiry
        'absolute_ttl' => 43200,
        'cookie_name'  => 'wbpms_session',
    ],
];
