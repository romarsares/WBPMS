<?php
/**
 * Verify Email — OTP entry form for the forgot-password flow.
 *
 * Shown after the user submits their email on /forgot-password. A 6-digit
 * code has been sent to their registered address; they must enter it here
 * before a password-reset link is issued.
 *
 * Variables injected by AuthController::showVerifyEmail():
 *   string      $csrfField     — rendered hidden CSRF input
 *   string      $pendingEmail  — the email address the code was sent to (masked for display)
 *   string|null $error         — error message from a previous failed attempt
 *   int|null    $remaining     — remaining allowed attempts (null if unknown)
 *
 * Panel revision Phase 2: email ownership verification before password reset.
 */
$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');

// Mask the email for display: show first 2 chars + *** + domain, e.g. jo***@example.com
$maskedEmail = '';
if (!empty($pendingEmail) && str_contains($pendingEmail, '@')) {
    [$localPart, $domain] = explode('@', $pendingEmail, 2);
    $visible     = min(2, strlen($localPart));
    $maskedEmail = substr($localPart, 0, $visible) . str_repeat('*', max(3, strlen($localPart) - $visible)) . '@' . $domain;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Email | Light Diamond Enterprises</title>
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
    </style>
</head>
<body class="login-page">

<div class="login-card">

    <img class="login-logo"
         src="<?= $base ?>/assets/light-diamond-logo.png"
         alt="Light Diamond Enterprises">

    <h1>Verify Your Email</h1>
    <p class="login-sub">
        We sent a 6-digit verification code to
        <strong><?= htmlspecialchars($maskedEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.
        Enter it below to continue.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert error" role="alert">
            <?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/forgot-password/verify" novalidate autocomplete="off">
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
            <p class="attempts-hint <?= $remaining <= 2 ? 'warning' : '' ?>">
                <?= (int) $remaining ?> attempt<?= $remaining === 1 ? '' : 's' ?> remaining
            </p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:1rem">
            Verify Code
        </button>
    </form>

    <p style="text-align:center;font-size:.875rem;margin:1rem 0 0;color:#4b5563">
        Didn't receive a code?
        <a href="<?= $base ?>/forgot-password">Request a new one</a>
    </p>

    <p style="font-size:.8125rem;color:#6b7280;text-align:center;margin:.5rem 0 0">
        The code expires in <strong>15 minutes</strong>.
    </p>

    <p class="login-footer" style="margin-top:1.5rem">
        <a href="<?= $base ?>/login" style="color:#4b5563;text-decoration:none;font-size:.875rem">
            &larr; Back to Login
        </a>
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
