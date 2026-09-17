<?php
/**
 * Mandatory first-login password change screen.
 *
 * Shown to auto-provisioned Employee accounts on their very first login.
 * The user cannot navigate away without completing the change.
 *
 * Variables injected by AuthController::showChangePassword():
 *   string      $csrfField  — rendered hidden CSRF input
 *   string|null $error      — validation error from previous submission
 *
 * Requirement 13, AC5–AC6.
 */
$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Your Password | Light Diamond Enterprises</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= @filemtime(APP_ROOT . '/public/assets/app.css') ?: time() ?>">
</head>
<body class="login-page">

<div class="login-card">

    <img class="login-logo"
         src="<?= $base ?>/assets/light-diamond-logo.png"
         alt="Light Diamond Enterprises">

    <h1>Set Your Password</h1>
    <p class="login-sub">
        Your account was created with a temporary password.<br>
        Please choose a permanent password to continue.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert error" role="alert">
            <?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/change-password" novalidate autocomplete="off">
        <?= $csrfField ?>

        <label for="password">New Password <span aria-hidden="true" style="color:#dc2626">*</span></label>
        <div class="login-pwd-wrap">
            <input type="password"
                   id="password"
                   name="password"
                   required
                   minlength="8"
                   autofocus
                   autocomplete="new-password"
                   placeholder="Minimum 8 characters">
            <button type="button"
                    class="login-pwd-toggle"
                    onclick="toggleField('password','icon1')"
                    aria-label="Show new password">
                <svg id="icon1" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        </div>

        <label for="password_confirm">Confirm Password <span aria-hidden="true" style="color:#dc2626">*</span></label>
        <div class="login-pwd-wrap">
            <input type="password"
                   id="password_confirm"
                   name="password_confirm"
                   required
                   minlength="8"
                   autocomplete="new-password"
                   placeholder="Repeat your new password">
            <button type="button"
                    class="login-pwd-toggle"
                    onclick="toggleField('password_confirm','icon2')"
                    aria-label="Show confirm password">
                <svg id="icon2" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:.75rem">
            Save Password &amp; Continue
        </button>
    </form>

    <p class="login-footer" style="margin-top:1rem;color:#6b7280;font-size:.8rem">
        You must set a new password before accessing the system.
    </p>

</div>

<script>
var SVG_EYE     = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
var SVG_EYE_OFF = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';

function toggleField(inputId, iconId) {
    var input = document.getElementById(inputId);
    var icon  = document.getElementById(iconId);
    var show  = input.type === 'password';
    input.type    = show ? 'text' : 'password';
    icon.innerHTML = show ? SVG_EYE_OFF : SVG_EYE;
    icon.closest('button').setAttribute('aria-label', show ? 'Hide password' : 'Show password');
}
</script>

</body>
</html>
