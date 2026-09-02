<?php


/**
 * 500 Internal Server Error
 *
 * @var string|null $detail  Developer-safe detail string (only shown in non-production).
 */

$detail ??= null;
$showDetail = ($_ENV['APP_ENV'] ?? 'production') !== 'production';

http_response_code(500);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 Server Error · WBPMS</title>
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
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
        }
        .code {
            font-size: 5rem;
            font-weight: 800;
            color: #d97706;
            line-height: 1;
            margin-bottom: .25rem;
        }
        h1 { font-size: 1.4rem; color: #111827; margin: .5rem 0 .75rem; }
        p { color: #6b7280; font-size: .95rem; margin: 0 0 1.5rem; }
        .detail {
            background: #fef9c3;
            border: 1px solid #fde68a;
            border-radius: 5px;
            padding: .75rem 1rem;
            font-family: 'Courier New', monospace;
            font-size: .8rem;
            color: #78350f;
            text-align: left;
            word-break: break-word;
            margin-bottom: 1.5rem;
        }
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
    <div class="code">500</div>
    <h1>Something Went Wrong</h1>
    <p>An unexpected error occurred. The issue has been logged.
       Please try again shortly, or contact the system administrator if the problem persists.</p>

    <?php if ($showDetail && $detail !== null): ?>
    <div class="detail"><?= htmlspecialchars((string) $detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>

    <a href="javascript:history.back()">Go back</a>
</div>
</body>
</html>
