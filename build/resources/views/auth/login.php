<?php


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
                    aria-label="Show password">
                <svg id="togglePwdIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
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
var SVG_EYE     = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
var SVG_EYE_OFF = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

function togglePwd() {
    var input  = document.getElementById('password');
    var btn    = document.getElementById('togglePassword');
    var isHidden = input.type === 'password';
    input.type     = isHidden ? 'text' : 'password';
    btn.innerHTML  = isHidden ? SVG_EYE_OFF : SVG_EYE;
    btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
}
</script>
</body>
</html>
