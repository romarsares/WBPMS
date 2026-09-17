<?php
/**
 * Password Reset — one-time credential display.
 *
 * Shown immediately after an HR Head or Business Owner resets a user's password.
 * The plain-text temporary password is shown exactly once and cannot be
 * retrieved after leaving this page.
 *
 * Variables injected by UserController::resetPassword():
 *   string $username      — the account whose password was reset
 *   string $tempPassword  — the new system-generated temporary password
 *   string $resetBy       — role name of the actor (HRHead | BusinessOwner)
 *
 * @var string $username
 * @var string $tempPassword
 * @var string $resetBy
 * @var string $base         injected by ViewRenderer
 * @var string $csrf         injected by ViewRenderer
 */

use Wbpms\Http\View\Formatter;
?>

<div class="page-header">
    <h1>Password Reset</h1>
</div>

<div class="card" style="max-width:520px">

    <div class="alert success" role="status" style="margin-bottom:1.25rem">
        The password for <strong><?= Formatter::escape($username) ?></strong>
        has been reset successfully.
    </div>

    <p style="margin:0 0 1rem">
        Share the new temporary credentials with the employee securely.
        <strong>This is the only time the temporary password will be shown.</strong>
    </p>

    <table class="data-table" style="margin-bottom:1.5rem">
        <tbody>
            <tr>
                <th scope="row" style="width:38%;font-weight:600">Username</th>
                <td>
                    <code id="cred-username" style="font-size:1rem;letter-spacing:.04em">
                        <?= Formatter::escape($username) ?>
                    </code>
                    <button type="button"
                            class="btn btn-sm btn-secondary"
                            style="margin-left:.5rem;padding:.15rem .5rem;font-size:.75rem"
                            onclick="copyText('cred-username', this)"
                            aria-label="Copy username">
                        Copy
                    </button>
                </td>
            </tr>
            <tr>
                <th scope="row" style="font-weight:600">Temporary Password</th>
                <td>
                    <code id="cred-password" style="font-size:1rem;letter-spacing:.04em">
                        <?= Formatter::escape($tempPassword) ?>
                    </code>
                    <button type="button"
                            class="btn btn-sm btn-secondary"
                            style="margin-left:.5rem;padding:.15rem .5rem;font-size:.75rem"
                            onclick="copyText('cred-password', this)"
                            aria-label="Copy temporary password">
                        Copy
                    </button>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="alert warning" role="note" style="font-size:.875rem;margin-bottom:1.5rem">
        <strong>Remind the employee:</strong> they will be required to set a new
        password the next time they log in. This temporary password cannot be
        recovered after leaving this page.
    </div>

    <a href="<?= $base ?>/users" class="btn btn-secondary">
        ← Back to User List
    </a>

</div>

<script>
function copyText(elementId, btn) {
    var text = document.getElementById(elementId).textContent.trim();
    if (!navigator.clipboard) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity  = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        btn.textContent = 'Copied';
        setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
        return;
    }
    navigator.clipboard.writeText(text).then(function() {
        btn.textContent = 'Copied';
        setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
    });
}
</script>
