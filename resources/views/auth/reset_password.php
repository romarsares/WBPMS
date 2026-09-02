<?php
/**
 * Reset Password view — set new password after OTP verification.
 * Variables: $csrfField, $error
 */
$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | Light Diamond Enterprises</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= @filemtime(APP_ROOT . '/public/assets/app.css') ?: time() ?>">
</head>
<body class="login-page">
<div class="login-card">
    <img class="login-logo" src="<?= $base ?>/assets/light-diamond-logo.png" alt="Light Diamond Enterprises">
    <h1>Reset Password</h1>
    <p class="login-sub">Choose a new password. Minimum 8 characters.</p>

    <?php if (!empty($error)): ?>
        <div class="alert error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/reset-password" novalidate>
        <?= $csrfField ?>
        <label for="password">New Password</label>
        <input type="password" id="password" name="password"
               required minlength="8" autofocus placeholder="Min. 8 characters">

        <label for="password_confirm">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm"
               required minlength="8" placeholder="Repeat new password">

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:.75rem">
            Set New Password
        </button>
    </form>

    <p class="login-footer">
        <a href="<?= $base ?>/login">← Back to Login</a>
    </p>
</div>
</body>
</html>
