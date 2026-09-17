<?php

use Wbpms\Http\View\Formatter;

/**
 * View: hr/requests/form
 *
 * HR Head submits a request on behalf of an employee.
 *
 * Variables injected by RequestsController::create()/store() + ViewRenderer:
 *   list<array{id:int,employee_number:string,last_name:string,first_name:string,branch_name:string}> $employees
 *   list<array{request_type_id:int,type_name:string}> $types
 *   array<string,string> $errors   — '_general' for service errors, field keys for field errors
 *   array<string,mixed>  $old      — repopulated POST values on re-render
 *   string $base
 *   string $csrf
 */

$employees ??= [];
$types     ??= [];
$errors    ??= [];
$old       ??= [];
$base      ??= '';
$csrf      ??= '';

// Helpers
$o   = static fn(string $key, string $fallback = ''): string =>
    Formatter::escape((string) ($old[$key] ?? $fallback));
$err = static fn(string $key): string => isset($errors[$key])
    ? '<span style="color:#dc2626;font-size:.8rem;display:block;margin-top:.2rem">'
      . Formatter::escape($errors[$key]) . '</span>'
    : '';

$selectedEmployee = (string) ($old['employee_id'] ?? '');
$selectedType     = (string) ($old['request_type_id'] ?? '');

// Build a JS map: request_type_id → code ('leave' | 'overtime' | 'cash_advance')
$codeMap  = ['Leave' => 'leave', 'Overtime' => 'overtime', 'CashAdvance' => 'cash_advance'];
$typeCodeMap = [];
foreach ($types as $t) {
    $typeCodeMap[(int) $t['request_type_id']] = $codeMap[$t['type_name']] ?? strtolower($t['type_name']);
}
?>

<div class="page-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
    <div>
        <h1 style="margin:0 0 .2rem">New Request</h1>
        <p style="margin:0;font-size:.875rem">
            <a href="<?= $base ?>/hr/requests" style="color:#6366f1">← Back to Requests</a>
        </p>
    </div>
</div>

<div class="card" style="max-width:640px;padding:1.5rem">

    <?php if (!empty($errors['_general'])): ?>
    <div class="alert error" role="alert"
         style="margin-bottom:1.25rem;padding:.75rem 1rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b">
        <?= Formatter::escape($errors['_general']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/hr/requests" novalidate>
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

        <!-- ── Employee picker ─────────────────────────────────────────── -->
        <div class="form-group" style="margin-bottom:1rem">
            <label for="employee_id"
                   style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                Employee <span style="color:#dc2626">*</span>
            </label>
            <select id="employee_id" name="employee_id"
                    style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['employee_id']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem"
                    required>
                <option value="">— select employee —</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= (int) $emp['id'] ?>"
                    <?= ($selectedEmployee === (string) $emp['id']) ? 'selected' : '' ?>>
                    <?= Formatter::escape($emp['last_name'] . ', ' . $emp['first_name']) ?>
                    <?php if (!empty($emp['branch_name']) && $emp['branch_name'] !== '—'): ?>
                        (<?= Formatter::escape($emp['branch_name']) ?>)
                    <?php endif; ?>
                    — <?= Formatter::escape($emp['employee_number']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?= $err('employee_id') ?>
        </div>

        <!-- ── Request type ────────────────────────────────────────────── -->
        <div class="form-group" style="margin-bottom:1rem">
            <label for="request_type_id"
                   style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                Request type <span style="color:#dc2626">*</span>
            </label>
            <select id="request_type_id" name="request_type_id"
                    style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['request_type_id']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem"
                    required>
                <option value="">— select type —</option>
                <?php foreach ($types as $t): ?>
                <option value="<?= (int) $t['request_type_id'] ?>"
                    <?= ($selectedType === (string) $t['request_type_id']) ? 'selected' : '' ?>>
                    <?= Formatter::escape($t['type_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?= $err('request_type_id') ?>
        </div>

        <!-- ── Leave fields ─────────────────────────────────────────────── -->
        <div id="section-leave" style="display:none">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem">
                <div>
                    <label for="start_date"
                           style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                        From date
                    </label>
                    <input type="date" id="start_date" name="start_date"
                           value="<?= $o('start_date') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['start_date']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('start_date') ?>
                </div>
                <div>
                    <label for="end_date"
                           style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                        To date
                    </label>
                    <input type="date" id="end_date" name="end_date"
                           value="<?= $o('end_date') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['end_date']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('end_date') ?>
                </div>
            </div>
        </div>

        <!-- ── Overtime fields ──────────────────────────────────────────── -->
        <div id="section-overtime" style="display:none">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-bottom:1rem">
                <div>
                    <label for="overtime_date"
                           style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                        OT date
                    </label>
                    <input type="date" id="overtime_date" name="overtime_date"
                           value="<?= $o('overtime_date') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['overtime_date']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('overtime_date') ?>
                </div>
                <div>
                    <label for="start_time"
                           style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                        Start time
                    </label>
                    <input type="time" id="start_time" name="start_time"
                           value="<?= $o('start_time') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['start_time']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('start_time') ?>
                </div>
                <div>
                    <label for="end_time"
                           style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                        End time
                    </label>
                    <input type="time" id="end_time" name="end_time"
                           value="<?= $o('end_time') ?>"
                           style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['end_time']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                    <?= $err('end_time') ?>
                </div>
            </div>
        </div>

        <!-- ── Cash advance fields ──────────────────────────────────────── -->
        <div id="section-cash-advance" style="display:none">
            <div style="max-width:280px;margin-bottom:1rem">
                <label for="amount_requested"
                       style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                    Amount requested (₱)
                </label>
                <input type="number" id="amount_requested" name="amount_requested"
                       value="<?= $o('amount_requested') ?>"
                       min="1" step="0.01"
                       style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['amount_requested']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem">
                <?= $err('amount_requested') ?>
            </div>
        </div>

        <!-- ── Reason ───────────────────────────────────────────────────── -->
        <div class="form-group" style="margin-bottom:1.25rem">
            <label for="reason"
                   style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                Reason / notes <span style="color:#dc2626">*</span>
            </label>
            <textarea id="reason" name="reason" rows="4"
                      placeholder="Describe the reason for this request..."
                      style="width:100%;padding:.45rem .7rem;border:1px solid <?= isset($errors['reason']) ? '#dc2626' : '#d1d5db' ?>;border-radius:4px;font-size:.9rem;resize:vertical"
                      required><?= $o('reason') ?></textarea>
            <?= $err('reason') ?>
        </div>

        <div style="display:flex;gap:.75rem">
            <button type="submit" class="btn btn-primary">Submit Request</button>
            <a href="<?= $base ?>/hr/requests" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var sel   = document.getElementById('request_type_id');
    var types = <?= json_encode($typeCodeMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    function update() {
        var code = types[sel.value] || '';
        document.getElementById('section-leave').style.display        = (code === 'leave')        ? '' : 'none';
        document.getElementById('section-overtime').style.display     = (code === 'overtime')     ? '' : 'none';
        document.getElementById('section-cash-advance').style.display = (code === 'cash_advance') ? '' : 'none';
    }

    sel.addEventListener('change', update);
    update(); // apply on page load when re-rendering with POST data
}());
</script>
