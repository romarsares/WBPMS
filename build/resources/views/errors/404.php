<?php


/**
 * 404 Not Found
 */

http_response_code(404);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 Not Found · WBPMS</title>
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
            color: #6366f1;
            line-height: 1;
            margin-bottom: .25rem;
        }
        h1 { font-size: 1.4rem; color: #111827; margin: .5rem 0 .75rem; }
        p { color: #6b7280; font-size: .95rem; margin: 0 0 1.5rem; }
        .actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }
        a {
            display: inline-block;
            padding: .55rem 1.25rem;
            border-radius: 5px;
            font-size: .9rem;
            text-decoration: none;
        }
        .btn-primary { background: #4f46e5; color: #fff; }
        .btn-primary:hover { background: #4338ca; }
        .btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
        .btn-secondary:hover { background: #e5e7eb; }
    </style>
</head>
<body>
<div class="card">
    <div class="code">404</div>
    <h1>Page Not Found</h1>
    <p>The page you are looking for does not exist or may have been moved.
       Check the address and try again.</p>
    <div class="actions">
        <a href="javascript:history.back()" class="btn-secondary">Go back</a>
        <a href="<?= rtrim((string)($_ENV['APP_BASE_URL'] ?? ''), '/') ?>/login" class="btn-primary">Home</a>
    </div>
</div>
</body>
</html>
