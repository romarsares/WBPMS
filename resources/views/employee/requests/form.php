<?php

use Wbpms\Http\View\Formatter;

/**
 * Employee portal — Submit a new request (leave, overtime, cash advance).
 *
 * @var list<array{id:int,name:string,code:string}> $requestTypes
 * @var int    $sickLeaveBalance    Remaining sick leave days
 * @var array<string,string> $errors   Key '_general' for service-level errors
 * @var string $csrf
 * @var string $base
 */

$requestTypes     ??= [];
$sickLeaveBalance ??= 0;
$cashAdvanceOutstanding ??= 0.0;
$errors           ??= [];
$csrf             ??= '';
$base             ??= '';

// Helper: re-populate a POST field safely
$fp  = static fn(string $key, string $fallback = ''): string =>
    Formatter::escape((string) ($_POST[$key] ?? $fallback));
// Helper: inline field error
$err = static fn(string $key): string => isset($errors[$key])
    ? '<span class="field-error" style="color:#dc2626;font-size:.8rem;display:block;margin-top:.25rem">'
      . Formatter::escape($errors[$key]) . '</span>'
    : '';

$selectedType = (string) ($_POST['request_type_id'] ?? '');
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
    <h1 style="margin:0">New Request</h1>
    <a href="<?= $base ?>/employee/requests" class="btn btn-secondary">← My Requests</a>
</div>

<div class="card" style="max-width:600px;padding:1.5rem">

    <!-- Leave balance and cash advance info panel -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.25rem">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.85rem">
            <div style="font-size:.7rem;color:#166534;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
                Sick Leave Balance
            </div>
            <div style="font-size:1.4rem;font-weight:700;color:#15803d">
                <?= number_format((float) $sickLeaveBalance, 1) ?>
                <span style="font-size:.8rem;font-weight:400">day<?= (float) $sickLeaveBalance !== 1.0 ? 's' : '' ?> remaining</span>
            </div>
            <div style="font-size:.75rem;color:#166534;margin-top:.2rem">
                Max 4 paid sick days per year
            </div>
        </div>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.85rem">
            <div style="font-size:.7rem;color:#1e40af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
                Outstanding Cash Advance
            </div>
            <div style="font-size:1.4rem;font-weight:700;color:<?= $cashAdvanceOutstanding > 0 ? '#dc2626' : '#15803d' ?>">
                ₱<?= number_format($cashAdvanceOutstanding, 2) ?>
            </div>
            <div style="font-size:.75rem;color:#1e40af;margin-top:.2rem">
                Unpaid balance (₱500/week deducted)
            </div>
        </div>
    </div>

    <?php if ($sickLeaveBalance <= 1): ?>
    <div class="alert warning" role="alert" style="margin-bottom:1rem;padding:.75rem 1rem;background:#fefce8;border:1px solid #fde047;border-radius:6px;font-size:.875rem;color:#713f12">
        ⚠ You have <strong><?= number_format((float) $sickLeaveBalance, 1) ?></strong> sick leave
        day<?= (float) $sickLeaveBalance !== 1.0 ? 's' : '' ?> remaining.
        A leave request will be blocked if your balance is insufficient.
    </div>
    <?php endif; ?>

    <?php if (!empty($errors['_general'])): ?>
    <div class="alert error" role="alert" style="margin-bottom:1rem">
        <?= Formatter::escape($errors['_general']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/employee/requests" novalidate>
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

        <!-- Request type -->
        <div class="form-group" style="margin-bottom:1rem">
            <label for="request_type_id" style="display:block;font-weight:500;margin-bottom:.3rem">
                Request type <span style="color:#dc2626">*</span>
            </label>
            <select id="request_type_id" name="request_type_id"
                    style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['request_type_id']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem"
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

        <!-- ── Leave fields ─────────────────────────────────────────────── -->
        <div id="section-leave" style="display:none">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem">
                <div class="form-group">
                    <label for="start_date" style="display:block;font-weight:500;margin-bottom:.3rem">From date</label>
                    <input type="date" id="start_date" name="start_date"
                           value="<?= $fp('start_date') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['start_date']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('start_date') ?>
                </div>
                <div class="form-group">
                    <label for="end_date" style="display:block;font-weight:500;margin-bottom:.3rem">To date</label>
                    <input type="date" id="end_date" name="end_date"
                           value="<?= $fp('end_date') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['end_date']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('end_date') ?>
                </div>
            </div>
        </div>

        <!-- ── Overtime fields ──────────────────────────────────────────── -->
        <div id="section-overtime" style="display:none">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-bottom:1rem">
                <div class="form-group">
                    <label for="overtime_date" style="display:block;font-weight:500;margin-bottom:.3rem">OT date</label>
                    <input type="date" id="overtime_date" name="overtime_date"
                           value="<?= $fp('overtime_date') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['overtime_date']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('overtime_date') ?>
                </div>
                <div class="form-group">
                    <label for="start_time" style="display:block;font-weight:500;margin-bottom:.3rem">Start time</label>
                    <input type="time" id="start_time" name="start_time"
                           value="<?= $fp('start_time') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['start_time']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('start_time') ?>
                </div>
                <div class="form-group">
                    <label for="end_time" style="display:block;font-weight:500;margin-bottom:.3rem">End time</label>
                    <input type="time" id="end_time" name="end_time"
                           value="<?= $fp('end_time') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['end_time']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('end_time') ?>
                </div>
            </div>
        </div>

        <!-- ── Cash advance fields ──────────────────────────────────────── -->
        <div id="section-cash-advance" style="display:none">
            <div class="form-group" style="max-width:280px;margin-bottom:1rem">
                <label for="amount_requested" style="display:block;font-weight:500;margin-bottom:.3rem">
                    Amount requested (₱)
                </label>
                <input type="number" id="amount_requested" name="amount_requested"
                       value="<?= $fp('amount_requested') ?>"
                       min="1" step="0.01"
                       style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['amount_requested']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                <?= $err('amount_requested') ?>
            </div>
        </div>

        <!-- Reason -->
        <div class="form-group" style="margin-bottom:1rem">
            <label for="reason" style="display:block;font-weight:500;margin-bottom:.3rem">
                Reason / notes <span style="color:#dc2626">*</span>
            </label>
            <textarea id="reason" name="reason" rows="4"
                      placeholder="Describe the reason for this request..."
                      style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['reason']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem;resize:vertical"
                      required><?= $fp('reason') ?></textarea>
            <?= $err('reason') ?>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.25rem">
            <button type="submit" class="btn btn-primary">Submit Request</button>
            <a href="<?= $base ?>/employee/requests" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var sel   = document.getElementById('request_type_id');
    var types = <?= json_encode(
        array_column($requestTypes, 'code', 'id'),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

    function update() {
        var code = types[sel.value] || '';
        document.getElementById('section-leave').style.display         = (code === 'leave')        ? '' : 'none';
        document.getElementById('section-overtime').style.display      = (code === 'overtime')     ? '' : 'none';
        document.getElementById('section-cash-advance').style.display  = (code === 'cash_advance') ? '' : 'none';
    }

    sel.addEventListener('change', update);
    update(); // apply on page load when re-rendering with POST data
}());
</script>
