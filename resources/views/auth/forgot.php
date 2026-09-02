<?php
/**
 * Forgot Password — enter registered email to receive OTP.
 * Variables: $csrfField, $error
 */
$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | Light Diamond Enterprises</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= @filemtime(APP_ROOT . '/public/assets/app.css') ?: time() ?>">
</head>
<body class="login-page">
<div class="login-card">
    <img class="login-logo" src="<?= $base ?>/assets/light-diamond-logo.png" alt="Light Diamond Enterprises">
    <h1>Forgot Password</h1>
    <p class="login-sub">Enter your account email to receive a one-time password.</p>

    <?php if (!empty($error)): ?>
        <div class="alert error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/forgot" novalidate>
        <?= $csrfField ?>
        <label for="account_email">Account Email</label>
        <input type="email" id="account_email" name="account_email"
               placeholder="your@email.com" required autofocus>
        <button type="submit" class="btn btn-primary btn-full">Send OTP</button>
    </form>

    <p class="login-footer"><a href="<?= $base ?>/login">← Back to Login</a></p>
</div>
</body>
</html>
