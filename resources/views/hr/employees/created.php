<?php
/**
 * Employee Created — credentials confirmation screen.
 *
 * Shown to the HR Head once, immediately after a new employee record is saved.
 * Displays the auto-provisioned username and temporary password so HR can
 * relay them to the employee securely.  The plain-text password is never
 * stored and will not be retrievable after this page is left.
 *
 * Variables injected by EmployeeController::store():
 *   string $employeeName  — full name of the newly created employee
 *   string $username      — derived login username  (e.g. juan.delacruz)
 *   string $tempPassword  — system-generated temporary password (shown once)
 *
 * Requirement 13, AC7: display credentials once in a dismissible confirmation;
 * AC8: no email delivery of credentials at this stage.
 *
 * @var string $employeeName
 * @var string $username
 * @var string $tempPassword
 * @var string $base          injected by ViewRenderer
 * @var string $csrf          injected by ViewRenderer
 */

use Wbpms\Http\View\Formatter;
?>

<div class="page-header">
    <h1>Employee Created</h1>
</div>

<div class="card" style="max-width:560px">

    <div class="alert success" role="status" style="margin-bottom:1.25rem">
        <strong><?= Formatter::escape($employeeName) ?></strong> has been added to the system
        and a login account has been created automatically.
    </div>

    <p style="margin:0 0 1rem">
        Share the credentials below with the employee securely.
        <strong>This is the only time the temporary password will be shown.</strong>
    </p>

    <table class="data-table" style="margin-bottom:1.5rem">
        <tbody>
            <tr>
                <th scope="row" style="width:40%;font-weight:600">Username</th>
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
        password the first time they log in. This temporary password cannot be
        recovered after leaving this page.
    </div>

    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
        <a href="<?= $base ?>/hr/employees/new" class="btn btn-primary">
            Add Another Employee
        </a>
        <a href="<?= $base ?>/hr/employees" class="btn btn-secondary">
            Back to Employee List
        </a>
    </div>

</div>

<script>
function copyText(elementId, btn) {
    var text = document.getElementById(elementId).textContent.trim();
    if (!navigator.clipboard) {
        // Fallback for older browsers
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
