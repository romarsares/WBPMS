<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Employee portal — Submit a new request (leave, overtime, cash advance).
 *
 * @var list<array{id:int,name:string,code:string}> $requestTypes
 * @var int    $sickLeaveBalance    Remaining sick leave days
 * @var array<string,string> $errors
 * @var string $csrf
 */

$requestTypes     ??= [];
$sickLeaveBalance ??= 0;
$errors           ??= [];
$csrf             ??= '';

$fp  = static fn(string $key, string $fallback = ''): string =>
    Formatter::escape((string) ($_POST[$key] ?? $fallback));
$err = static fn(string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . Formatter::escape($errors[$key]) . '</span>' : '';

$selectedType = (string) ($_POST['request_type_id'] ?? '');
?>

<div class="page-header">
    <h1>New Request</h1>
    <a href="/employee/requests" class="btn btn-secondary">← My Requests</a>
</div>

<div class="card" style="max-width:580px">

    <?php if ($sickLeaveBalance <= 1): ?>
    <div class="flash flash-warning" style="margin-bottom:1rem">
        You have <strong><?= $sickLeaveBalance ?></strong> sick leave
        day<?= $sickLeaveBalance !== 1 ? 's' : '' ?> remaining this year.
        A leave request will be rejected if your balance is insufficient.
    </div>
    <?php endif; ?>

    <form method="POST" action="/employee/requests">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

        <div class="form-group">
            <label for="request_type_id">Request type <span style="color:#dc2626">*</span></label>
            <select id="request_type_id" name="request_type_id"
                class="<?= isset($errors['request_type_id']) ? 'is-invalid' : '' ?>"
                required>
                <option value="">— select type —</option>
                <?php foreach ($requestTypes as $type): ?>
                <option value="<?= (int) $type['id'] ?>"
                    <?= ($selectedType === (string) $type['id']) ? 'selected' : '' ?>>
                    <?= Formatter::escape($type['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?= $err('request_type_id') ?>
        </div>

        <!-- Leave fields -->
        <div id="section-leave" style="display:none">
            <div class="form-row">
                <div class="form-group">
                    <label for="leave_start">From date</label>
                    <input type="date" id="leave_start" name="leave_start" value="<?= $fp('leave_start') ?>">
                    <?= $err('leave_start') ?>
                </div>
                <div class="form-group">
                    <label for="leave_end">To date</label>
                    <input type="date" id="leave_end" name="leave_end" value="<?= $fp('leave_end') ?>">
                    <?= $err('leave_end') ?>
                </div>
            </div>
        </div>

        <!-- Overtime fields -->
        <div id="section-overtime" style="display:none">
            <div class="form-row">
                <div class="form-group">
                    <label for="ot_date">Overtime date</label>
                    <input type="date" id="ot_date" name="ot_date" value="<?= $fp('ot_date') ?>">
                    <?= $err('ot_date') ?>
                </div>
                <div class="form-group">
                    <label for="ot_hours">Estimated hours</label>
                    <input type="number" id="ot_hours" name="ot_hours"
                        value="<?= $fp('ot_hours') ?>" min="1" max="12" step="0.5">
                    <?= $err('ot_hours') ?>
                </div>
            </div>
        </div>

        <!-- Cash advance fields -->
        <div id="section-cash-advance" style="display:none">
            <div class="form-group" style="max-width:260px">
                <label for="ca_amount">Amount requested (₱)</label>
                <input type="number" id="ca_amount" name="ca_amount"
                    value="<?= $fp('ca_amount') ?>" min="1" step="0.01">
                <?= $err('ca_amount') ?>
            </div>
        </div>

        <div class="form-group">
            <label for="reason">Reason / notes <span style="color:#dc2626">*</span></label>
            <textarea id="reason" name="reason" rows="4"
                placeholder="Describe the reason for this request..."
                class="<?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                required><?= $fp('reason') ?></textarea>
            <?= $err('reason') ?>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="flash flash-error" role="alert">
            Please correct the highlighted fields.
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:.75rem;margin-top:1rem">
            <button type="submit" class="btn btn-primary">Submit Request</button>
            <a href="/employee/requests" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// Show/hide type-specific sections based on selected request type
(function () {
    const sel   = document.getElementById('request_type_id');
    const types = <?= json_encode(
        array_column($requestTypes, 'code', 'id'),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

    function update() {
        const code = types[sel.value] || '';
        document.getElementById('section-leave').style.display        = code === 'leave'        ? '' : 'none';
        document.getElementById('section-overtime').style.display     = code === 'overtime'     ? '' : 'none';
        document.getElementById('section-cash-advance').style.display = code === 'cash_advance' ? '' : 'none';
    }

    sel.addEventListener('change', update);
    update();
}());
</script>
