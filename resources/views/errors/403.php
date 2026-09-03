<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/** @var string $base */
/** @var string $roleName */
$dashboardPath = match ($roleName) {
    'BusinessOwner' => '/owner/dashboard',
    'HRHead'        => '/hr/dashboard',
    default         => '/employee/dashboard',
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied | Light Diamond Enterprises</title>
    <link rel="stylesheet" href="<?= Formatter::escape($base) ?>/assets/app.css?v=<?= filemtime(APP_ROOT . '/public/assets/app.css') ?>">
</head>
<body style="min-height:100vh;display:grid;place-items:center;background:#f3f4f6;margin:0;padding:1.5rem">
    <main class="card" style="max-width:560px;width:100%;text-align:center;padding:2.25rem">
        <div aria-hidden="true" style="font-size:3rem;line-height:1;margin-bottom:.75rem">&#128274;</div>
        <p style="margin:0;color:#b45309;font-weight:700;letter-spacing:.08em;font-size:.8rem">ERROR 403</p>
        <h1 style="margin:.45rem 0 .75rem">You don’t have access to this page</h1>
        <p class="muted" style="margin:0 auto 1.5rem;max-width:430px">
            Your <strong><?= Formatter::escape($roleName) ?></strong> account does not have permission to perform this action.
            If you think this is incorrect, ask the Business Owner to review your account role.
        </p>
        <a href="<?= Formatter::escape($base . $dashboardPath) ?>" class="btn btn-primary">Go to my dashboard</a>
    </main>
</body>
</html>
