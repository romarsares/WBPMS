<?php
/**
 * Forgot Password — email-entry form.
 *
 * Variables injected by AuthController::showForgotPassword():
 *   string      $csrfField  — rendered hidden CSRF input
 *   string|null $error      — validation error from previous submission
 *
 * REQ002: forgot-password email-based reset-link flow.
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

    <img class="login-logo"
         src="<?= $base ?>/assets/light-diamond-logo.png"
         alt="Light Diamond Enterprises">

    <h1>Forgot Password</h1>
    <p class="login-sub">
        Enter your registered email address and we'll send you a link to reset your password.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert error" role="alert">
            <?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/forgot-password" novalidate autocomplete="off">
        <?= $csrfField ?>

        <label for="email">Email Address</label>
        <input type="text"
               id="email"
               name="email"
               value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
               autocomplete="email"
               required
               autofocus
               placeholder="Enter your registered email address">

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:.25rem">
            Send Reset Link
        </button>
    </form>

    <p class="login-footer" style="margin-top:1.25rem">
        <a href="<?= $base ?>/login" style="color:#4b5563;text-decoration:none;font-size:.875rem">
            &larr; Back to Login
        </a>
    </p>

</div>

</body>
</html>
