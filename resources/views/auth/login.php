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
    <link rel="stylesheet" href="<?= $base ?>/assets/app.css">
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
        <div style="position:relative">
            <input type="password"
                   id="password"
                   name="password"
                   autocomplete="current-password"
                   required
                   placeholder="Enter your password"
                   style="padding-right:48px">
            <button type="button"
                    id="togglePassword"
                    onclick="togglePwd()"
                    aria-label="Show password"
                    style="position:absolute;right:0;top:0;bottom:0;width:44px;
                           background:none;border:none;cursor:pointer;
                           font-size:18px;color:#888;padding:0">
                👁
            </button>
        </div>

        <button type="submit" class="btn-primary btn-full" style="margin-top:18px">
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
