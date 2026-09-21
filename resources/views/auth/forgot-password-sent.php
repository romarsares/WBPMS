<?php
/**
 * Forgot Password — confirmation screen shown after submission.
 *
 * This view is shown regardless of whether the email exists, to prevent
 * account-existence enumeration.
 *
 * No variables injected.
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
    <title>Reset Link Sent | Light Diamond Enterprises</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= @filemtime(APP_ROOT . '/public/assets/app.css') ?: time() ?>">
</head>
<body class="login-page">

<div class="login-card">

    <img class="login-logo"
         src="<?= $base ?>/assets/light-diamond-logo.png"
         alt="Light Diamond Enterprises">

    <h1>Check Your Email</h1>
    <p class="login-sub">
        If that email address is registered in the system, you will receive a
        password reset link within a few minutes.
    </p>

    <p style="font-size:.875rem;color:#4b5563;margin:0 0 .5rem">
        The link will expire in <strong>60 minutes</strong>.
        If you don't see the email, check your spam or junk folder.
    </p>

    <p style="font-size:.875rem;color:#4b5563;margin:0">
        No email? Contact your HR or system administrator to have your password
        reset manually.
    </p>

    <p class="login-footer" style="margin-top:1.75rem">
        <a href="<?= $base ?>/login" style="color:#4b5563;text-decoration:none;font-size:.875rem">
            &larr; Back to Login
        </a>
    </p>

</div>

</body>
</html>
