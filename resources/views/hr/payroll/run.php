<?php
/**
 * View: hr/payroll/run  (GET /hr/payroll/create)
 * Variables: $periods (list from PayrollService::periods()),
 *            $branches, $errors
 */
$errors ??= [];
?>
<div class="page-head">
    <div>
        <h1>New Payroll Run</h1>
        <p><a href="<?= $base ?>/hr/payroll">← Back to Payroll</a></p>
    </div>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-error" style="margin-bottom:1rem;padding:.75rem 1rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b">
    <?php foreach ($errors as $e): ?><p style="margin:0"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:520px">
    <form method="post" action="<?= $base ?>/hr/payroll">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-group" style="margin-bottom:1rem">
            <label for="payroll_period_id" style="display:block;font-weight:500;margin-bottom:.25rem">
                Payroll Period <span style="color:#ef4444">*</span>
            </label>
            <select id="payroll_period_id" name="payroll_period_id" class="form-control" required>
                <option value="">— Select Period —</option>
                <?php foreach ($periods as $p): ?>
                <option value="<?= (int)$p['payroll_period_id'] ?>">
                    <?= htmlspecialchars($p['label']) ?>
                    <?php if ($p['status'] !== 'Open'): ?>
                    (<?= htmlspecialchars($p['status']) ?>)
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:1.25rem">
            <label for="branch_id" style="display:block;font-weight:500;margin-bottom:.25rem">
                Branch <span style="color:#ef4444">*</span>
            </label>
            <select id="branch_id" name="branch_id" class="form-control" required>
                <option value="">— Select Branch —</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= (int)$b['branch_id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small style="color:#6b7280">Eligible employees are those assigned to this branch at the period start date.</small>
        </div>

        <div style="display:flex;gap:.75rem">
            <button type="submit" class="btn btn-primary">Create Draft Run</button>
            <a href="<?= $base ?>/hr/payroll" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
