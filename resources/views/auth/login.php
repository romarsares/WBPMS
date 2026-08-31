<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — WBPMS</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f4f5f7;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 2.5rem;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }
        h1 { font-size: 1.4rem; margin: 0 0 .25rem; color: #1a202c; }
        p.sub { color: #718096; font-size: .9rem; margin: 0 0 1.5rem; }
        label { display: block; font-size: .875rem; font-weight: 500; color: #374151; margin-bottom: .35rem; }
        input[type=text], input[type=password] {
            width: 100%;
            padding: .6rem .75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: .95rem;
            outline: none;
            transition: border-color .15s;
            margin-bottom: 1rem;
        }
        input:focus { border-color: #4f46e5; box-shadow: 0 0 0 2px rgba(79,70,229,.2); }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 4px;
            padding: .6rem .75rem;
            font-size: .875rem;
            margin-bottom: 1rem;
        }
        button[type=submit] {
            width: 100%;
            background: #4f46e5;
            color: #fff;
            border: none;
            padding: .7rem;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background .15s;
        }
        button:hover { background: #4338ca; }
        .disclaimer {
            font-size: .75rem;
            color: #9ca3af;
            text-align: center;
            margin-top: 1.25rem;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>WBPMS Sign In</h1>
    <p class="sub">Web-Based Payroll Management System</p>

    <?php if (!empty($error)): ?>
        <div class="error" role="alert">
            <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/login" autocomplete="off" novalidate>
        <?= $csrfField ?>

        <label for="username">Username</label>
        <input
            type="text"
            id="username"
            name="username"
            value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            autocomplete="username"
            required
            autofocus
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">Sign In</button>
    </form>

    <p class="disclaimer">
        Internal use only. All access is logged and audited.
    </p>
</div>
</body>
</html>
