<?php
/**
 * OTP Verification view.
 * Variables: $csrfField, $error, $devOtp (dev mode only)
 */
$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
$appEnv = $_ENV['APP_ENV'] ?? 'production';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enter OTP | Light Diamond Enterprises</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= @filemtime(APP_ROOT . '/public/assets/app.css') ?: time() ?>">
</head>
<body class="login-page">
<div class="login-card">
    <img class="login-logo" src="<?= $base ?>/assets/light-diamond-logo.png" alt="Light Diamond Enterprises">
    <h1>Enter OTP</h1>
    <p class="login-sub">Enter the 6-digit code. It expires in 15 minutes.</p>

    <?php if ($appEnv === 'development' && !empty($devOtp)): ?>
        <div class="alert" style="background:#fef9c3;border:1px solid #facc15;color:#713f12;margin-bottom:1rem;padding:.6rem .85rem;border-radius:6px;font-size:.875rem">
            <strong>Development mode — OTP:</strong> <?= htmlspecialchars($devOtp) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/reset-otp" novalidate>
        <?= $csrfField ?>
        <label for="otp">One-Time Password</label>
        <input type="text" id="otp" name="otp"
               inputmode="numeric" maxlength="6" pattern="\d{6}"
               placeholder="000000" required autofocus
               style="letter-spacing:.35em;font-size:1.4rem;text-align:center">
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:.75rem">Verify OTP</button>
    </form>

    <p class="login-footer"><a href="<?= $base ?>/forgot">← Request new OTP</a></p>
</div>
</body>
</html>
