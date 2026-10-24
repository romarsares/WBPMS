<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR — Employee bank account form.
 *
 * @var array<string, mixed>      $employee
 * @var array<string, mixed>|null $bank     Current active bank record, or null if none
 * @var array<string, string>     $errors
 * @var string                    $base
 * @var string                    $csrf
 */

$employee ??= [];
$bank     ??= null;
$errors   ??= [];
$csrf     ??= '';

$empId   = (int) $employee['id'];
$empName = Formatter::escape($employee['last_name'] . ', ' . $employee['first_name']);

// Populate from posted data first, then existing bank record, then defaults
$pv = static function (string $key, string $bankKey = '', string $fallback = '') use ($bank): string {
    if (isset($_POST[$key])) {
        return Formatter::escape((string) $_POST[$key]);
    }
    $bk = $bankKey !== '' ? $bankKey : $key;
    return $bank !== null && isset($bank[$bk])
        ? Formatter::escape((string) $bank[$bk])
        : Formatter::escape($fallback);
};

$err = static fn(string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . Formatter::escape($errors[$key]) . '</span>' : '';

// Default effective_from to today if no existing record
$defaultEffective = $bank !== null
    ? (string) $bank['effective_from']
    : date('Y-m-d');

$BANK_NAMES = ['BDO', 'BPI', 'Metrobank', 'UnionBank', 'Landbank', 'PNB', 'RCBC', 'Security Bank', 'Other'];
?>

<div class="page-header">
    <div>
        <h1>Bank Account</h1>
        <div style="font-size:.875rem;color:#6b7280"><?= $empName ?></div>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <a href="<?= $base ?>/hr/employees/<?= $empId ?>" class="btn btn-secondary">← Back to Employee</a>
    </div>
</div>

<?php if ($bank !== null): ?>
<div class="flash flash-info" role="status" style="margin-bottom:1.25rem">
    Current active account:
    <strong><?= Formatter::escape((string) $bank['bank_name']) ?></strong>
    &mdash;
    <?= Formatter::escape((string) $bank['account_name']) ?>
    (<?= Formatter::escape((string) $bank['account_number']) ?>),
    effective <?= Formatter::escape(date('m/d/Y', strtotime((string) $bank['effective_from']))) ?>.
    Saving a new account will supersede this one.
</div>
<?php else: ?>
<div class="flash flash-warning" role="status" style="margin-bottom:1.25rem">
    No bank account is on file for this employee. Payroll disbursement preparation requires an active bank account.
</div>
<?php endif; ?>

<div class="card" style="max-width:600px">
    <form method="POST"
          action="<?= Formatter::escape($base . '/hr/employees/' . $empId . '/bank') ?>"
          novalidate>
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

        <h2 style="margin-top:0">Bank Account Details</h2>

        <div class="form-row">
            <div class="form-group">
                <label for="bank_name">Bank name <span style="color:#dc2626">*</span></label>
                <?php
                $selectedBank = $pv('bank_name', 'bank_name', 'BDO');
                $isOther = !in_array($selectedBank, $BANK_NAMES, true) && $selectedBank !== '';
                $selectVal = $isOther ? 'Other' : $selectedBank;
                ?>
                <select id="bank_name_select" onchange="syncBankName(this)"
                        class="<?= isset($errors['bank_name']) ? 'is-invalid' : '' ?>">
                    <?php foreach ($BANK_NAMES as $bname): ?>
                    <option value="<?= Formatter::escape($bname) ?>"
                            <?= $selectVal === $bname ? 'selected' : '' ?>>
                        <?= Formatter::escape($bname) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="bank_name" name="bank_name"
                       value="<?= $selectedBank ?>"
                       placeholder="Type bank name"
                       style="margin-top:.4rem;<?= ($selectVal !== 'Other') ? 'display:none' : '' ?>"
                       class="<?= isset($errors['bank_name']) ? 'is-invalid' : '' ?>">
                <?= $err('bank_name') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="account_name">Account name <span style="color:#dc2626">*</span></label>
                <input type="text" id="account_name" name="account_name"
                       value="<?= $pv('account_name') ?>"
                       placeholder="Full name as on the bank account"
                       maxlength="100"
                       class="<?= isset($errors['account_name']) ? 'is-invalid' : '' ?>">
                <?= $err('account_name') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="account_number">Account number <span style="color:#dc2626">*</span></label>
                <input type="text" id="account_number" name="account_number"
                       value="<?= $pv('account_number') ?>"
                       placeholder="e.g. 1234-5678-90"
                       inputmode="numeric"
                       class="<?= isset($errors['account_number']) ? 'is-invalid' : '' ?>">
                <?= $err('account_number') ?>
            </div>
            <div class="form-group">
                <label for="account_type">Account type</label>
                <?php $currentType = $pv('account_type', 'account_type', ''); ?>
                <select id="account_type" name="account_type">
                    <option value=""         <?= $currentType === ''         ? 'selected' : '' ?>>— optional —</option>
                    <option value="Savings"  <?= $currentType === 'Savings'  ? 'selected' : '' ?>>Savings</option>
                    <option value="Checking" <?= $currentType === 'Checking' ? 'selected' : '' ?>>Checking</option>
                    <option value="Payroll"  <?= $currentType === 'Payroll'  ? 'selected' : '' ?>>Payroll</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="effective_from">Effective date <span style="color:#dc2626">*</span></label>
                <input type="date" id="effective_from" name="effective_from"
                       value="<?= isset($_POST['effective_from'])
                           ? Formatter::escape((string) $_POST['effective_from'])
                           : Formatter::escape($defaultEffective) ?>"
                       class="<?= isset($errors['effective_from']) ? 'is-invalid' : '' ?>">
                <small class="muted">Date this account becomes active for payroll disbursement.</small>
                <?= $err('effective_from') ?>
            </div>
        </div>

        <?php if ($errors !== []): ?>
        <div class="flash flash-error" role="alert">
            Please correct the highlighted fields before saving.
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:.75rem;margin-top:1.25rem">
            <button type="submit" class="btn btn-primary">Save Bank Account</button>
            <a href="<?= $base ?>/hr/employees/<?= $empId ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
function syncBankName(sel) {
    var input = document.getElementById('bank_name');
    if (sel.value === 'Other') {
        input.style.display = '';
        input.focus();
    } else {
        input.style.display = 'none';
        input.value = sel.value;
    }
}
// Sync on page load so the hidden input always carries the correct value
(function () {
    var sel = document.getElementById('bank_name_select');
    var input = document.getElementById('bank_name');
    if (sel.value !== 'Other') {
        input.value = sel.value;
    }
})();
</script>

<style>
.flash-info    { background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; border-radius: .375rem; padding: .75rem 1rem; }
.flash-warning { background: #fef3c7; border: 1px solid #fbbf24; color: #92400e; border-radius: .375rem; padding: .75rem 1rem; }
.flash-error   { background: #fee2e2; border: 1px solid #f87171; color: #991b1b; border-radius: .375rem; padding: .75rem 1rem; }
</style>
