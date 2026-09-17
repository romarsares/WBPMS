<?php
/**
 * View: hr/salary/form
 * Variables: $salary (array|null), $employees, $history (array|null),
 *            $errors, $formTitle, $formAction, $old (array|null)
 */
$old      = $old ?? ($salary ?? []);
$history  = $history ?? [];
$isEdit   = $salary !== null;
?>
<div class="page-head">
    <div>
        <h1><?= htmlspecialchars($formTitle) ?></h1>
        <p><a href="<?= $base ?>/hr/salary">← Back to Salary Management</a></p>
    </div>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-error" style="margin-bottom:1rem;padding:.75rem 1rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b">
    <?php foreach ($errors as $e): ?><p style="margin:0"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:<?= $isEdit ? '1fr 1fr' : '1fr' ?>;gap:1.5rem">

    <div class="card">
        <form method="post" action="<?= htmlspecialchars($formAction) ?>">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <?php if (!$isEdit): ?>
            <div class="form-group" style="margin-bottom:1rem">
                <label for="employee_id" style="display:block;font-weight:500;margin-bottom:.25rem">
                    Employee <span style="color:#ef4444">*</span>
                </label>
                <select id="employee_id" name="employee_id" class="form-control" required>
                    <option value="">— Select Employee —</option>
                    <?php foreach ($employees as $e): ?>
                    <option value="<?= (int)$e['employee_id'] ?>"
                        <?= (string)($old['employee_id'] ?? '') === (string)$e['employee_id'] ? ' selected' : '' ?>>
                        <?= htmlspecialchars($e['employee_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="form-group" style="margin-bottom:1rem">
                <label style="display:block;font-weight:500;margin-bottom:.25rem;color:#6b7280">Employee</label>
                <p style="padding:.5rem 0;font-weight:600"><?= htmlspecialchars($salary['employee_name']) ?></p>
            </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom:1rem">
                <label for="daily_rate" style="display:block;font-weight:500;margin-bottom:.25rem">
                    Daily Rate (₱) <span style="color:#ef4444">*</span>
                </label>
                <input type="number" id="daily_rate" name="daily_rate" class="form-control"
                       step="0.01" min="0.01" required
                       value="<?= htmlspecialchars((string)($old['daily_rate'] ?? '')) ?>">
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
                <label for="effective_from" style="display:block;font-weight:500;margin-bottom:.25rem">
                    Effective From <span style="color:#ef4444">*</span>
                </label>
                <input type="date" id="effective_from" name="effective_from" class="form-control" required
                       value="<?= htmlspecialchars((string)($old['effective_from'] ?? date('Y-m-d'))) ?>">
                <?php if ($isEdit): ?>
                <small style="color:#6b7280">A new record will be created; the current record will be closed the day before this date.</small>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:.75rem">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save New Rate' : 'Create Record' ?></button>
                <a href="<?= $base ?>/hr/salary" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php if ($isEdit && $history !== []): ?>
    <div class="card">
        <h3 style="margin:0 0 1rem">Rate History</h3>
        <table class="data-table" style="font-size:.875rem">
            <thead>
                <tr>
                    <th>Daily Rate</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
            <tr<?= (int)$h['salary_id'] === (int)$salary['salary_id'] ? ' style="background:#f0fdf4"' : '' ?>>
                <td>₱<?= number_format((float)$h['daily_rate'], 2) ?></td>
                <td><?= htmlspecialchars($h['effective_from']) ?></td>
                <td><?= $h['effective_to'] ? htmlspecialchars($h['effective_to']) : '—' ?></td>
                <td>
                    <?php $c=['Active'=>'#10b981','Superseded'=>'#f59e0b','Archived'=>'#6b7280'][$h['status']]??'#6b7280'; ?>
                    <span style="background:<?=$c?>;color:#fff;padding:1px 7px;border-radius:9999px;font-size:.7rem"><?= htmlspecialchars($h['status']) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
