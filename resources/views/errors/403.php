<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * 403 Forbidden
 *
 * @var string|null $message  Optional context message (safe to display)
 */

$message ??= null;

http_response_code(403);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 Forbidden · WBPMS</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f4f5f7;
            color: #1a202c;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 2.5rem 3rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
        }
        .code {
            font-size: 5rem;
            font-weight: 800;
            color: #dc2626;
            line-height: 1;
            margin-bottom: .25rem;
        }
        h1 { font-size: 1.4rem; color: #111827; margin: .5rem 0 .75rem; }
        p { color: #6b7280; font-size: .95rem; margin: 0 0 1.5rem; }
        a {
            display: inline-block;
            padding: .55rem 1.25rem;
            background: #4f46e5;
            color: #fff;
            border-radius: 5px;
            font-size: .9rem;
            text-decoration: none;
        }
        a:hover { background: #4338ca; }
    </style>
</head>
<body>
<div class="card">
    <div class="code">403</div>
    <h1>Access Denied</h1>
    <p>
        <?php if ($message !== null): ?>
            <?= Formatter::escape($message) ?>
        <?php else: ?>
            You do not have permission to access this page.
            If you believe this is an error, please contact the HR Head or system administrator.
        <?php endif; ?>
    </p>
    <a href="javascript:history.back()">Go back</a>
</div>
</body>
</html>
