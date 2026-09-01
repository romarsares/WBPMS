<?php

declare(strict_types=1);

/**
 * Login view — Light Diamond Enterprises design.
 *
 * Variables injected by AuthController::showLogin():
 *   string|null $error      — authentication error message
 *   string      $csrfField  — hidden CSRF input HTML
 */

$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Light Diamond Enterprises</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= @filemtime(APP_ROOT . '/public/assets/app.css') ?: time() ?>">
</head>
<body class="login-page">

<div class="login-card">

    <img class="login-logo"
         src="<?= $base ?>/assets/light-diamond-logo.png"
         alt="Light Diamond Enterprises">

    <h1>Light Diamond</h1>
    <p class="login-sub">Enterprises Payroll &amp; Employee Management System</p>

    <?php if (!empty($error)): ?>
        <div class="alert error" role="alert">
            <?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/login" autocomplete="off" novalidate>
        <?= $csrfField ?>

        <label for="username">Username</label>
        <input type="text"
               id="username"
               name="username"
               value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
               autocomplete="username"
               required
               autofocus
               placeholder="Enter your username">

        <label for="password">Password</label>
        <div class="login-pwd-wrap">
            <input type="password"
                   id="password"
                   name="password"
                   autocomplete="current-password"
                   required
                   placeholder="Enter your password">
            <button type="button"
                    id="togglePassword"
                    class="login-pwd-toggle"
                    onclick="togglePwd()"
                    aria-label="Show password">👁</button>
        </div>

        <button type="submit" class="btn btn-primary btn-full">
            Login
        </button>
    </form>

    <p class="login-footer">
        Internal use only. All access is logged and audited.
    </p>

</div>

<script>
function togglePwd() {
    var input  = document.getElementById('password');
    var btn    = document.getElementById('togglePassword');
    var isHidden = input.type === 'password';
    input.type     = isHidden ? 'text' : 'password';
    btn.textContent = isHidden ? '🙈' : '👁';
    btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
}
</script>
</body>
</html>
