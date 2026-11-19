<?php
/**
 * Verify New Email — one-time OTP confirmation screen shown to Employee
 * accounts after they complete their first-login password change.
 *
 * The employee must enter the 6-digit code sent to their registered email
 * address.  Once verified, is_email_verified is set to 1 and they are
 * redirected to their dashboard.  This screen is never shown again.
 *
 * Variables injected by AuthController::showVerifyNewEmail():
 *   string      $csrfField    — rendered hidden CSRF input
 *   string      $maskedEmail  — email address masked for display (e.g. ju***@example.com)
 *   string|null $error        — error message from a previous failed attempt
 *   string|null $success      — success message after a resend
 *   int|null    $remaining    — remaining allowed attempts (null if unknown)
 */
$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Your Email | Light Diamond Enterprises</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $base ?>/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $base ?>/favicon-16.png">
    <link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= @filemtime(APP_ROOT . '/public/assets/app.css') ?: time() ?>">
    <style>
        .otp-input {
            letter-spacing: .35em;
            font-size: 1.5rem;
            text-align: center;
            font-weight: 600;
        }
        .attempts-hint {
            font-size: .8125rem;
            color: #6b7280;
            text-align: center;
            margin: .4rem 0 0;
        }
        .attempts-hint.warning {
            color: #b45309;
        }
        .resend-form {
            text-align: center;
            margin-top: .75rem;
        }
        .resend-form button {
            background: none;
            border: none;
            color: #2563eb;
            cursor: pointer;
            font-size: .875rem;
            padding: 0;
            text-decoration: underline;
        }
        .resend-form button:hover {
            color: #1d4ed8;
        }
    </style>
</head>
<body class="login-page">

<div class="login-card">

    <img class="login-logo"
         src="<?= $base ?>/assets/light-diamond-logo.png"
         alt="Light Diamond Enterprises">

    <h1>Verify Your Email</h1>
    <p class="login-sub">
        Your password has been set. To complete your account setup, enter the
        6-digit code we sent to
        <strong><?= htmlspecialchars((string) ($maskedEmail ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert error" role="alert">
            <?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert success" role="status">
            <?= htmlspecialchars((string) $success, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/verify-email" novalidate autocomplete="off">
        <?= $csrfField ?>

        <label for="code">Verification Code</label>
        <input type="text"
               id="code"
               name="code"
               class="otp-input"
               inputmode="numeric"
               pattern="[0-9]{6}"
               maxlength="6"
               required
               autofocus
               autocomplete="one-time-code"
               placeholder="000000">

        <?php if ($remaining !== null): ?>
            <p class="attempts-hint <?= (int) $remaining <= 2 ? 'warning' : '' ?>">
                <?= (int) $remaining ?> attempt<?= (int) $remaining === 1 ? '' : 's' ?> remaining
            </p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:1rem">
            Verify &amp; Continue
        </button>
    </form>

    <div class="resend-form">
        <form method="POST" action="<?= $base ?>/verify-email/resend">
            <?= $csrfField ?>
            <p style="font-size:.875rem;color:#4b5563;margin:0 0 .25rem">
                Didn't receive a code?
            </p>
            <button type="submit">Resend verification code</button>
        </form>
    </div>

    <p style="font-size:.8125rem;color:#6b7280;text-align:center;margin:.75rem 0 0">
        The code expires in <strong>15 minutes</strong>. You will only need to do
        this once.
    </p>

    <p class="login-footer" style="margin-top:1.5rem">
        <form method="POST" action="<?= $base ?>/logout" style="display:inline">
            <?= $csrfField ?>
            <button type="submit"
                    style="background:none;border:none;color:#4b5563;cursor:pointer;
                           font-size:.875rem;text-decoration:none;padding:0">
                &larr; Log out
            </button>
        </form>
    </p>

</div>

<script>
// Auto-submit when the user finishes typing 6 digits.
document.getElementById('code').addEventListener('input', function () {
    var val = this.value.replace(/\D/g, '').slice(0, 6);
    this.value = val;
    if (val.length === 6) {
        this.closest('form').submit();
    }
});
</script>

</body>
</html>
