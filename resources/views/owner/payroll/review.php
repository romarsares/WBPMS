<?php
/**
 * View: owner/payroll/review  (GET /owner/payroll/{id}/review)
 * Variables: $run (from PayrollService::findRunOrFail()), $details, $errors
 */
$errors  ??= [];
$details ??= [];
$runId   = (int)$run['payroll_run_id'];
$isPending = $run['status'] === 'PendingOwnerApproval';
$grossTotal = array_sum(array_column($details, 'gross_pay'));
$netTotal   = array_sum(array_column($details, 'net_pay'));
$dedTotal   = array_sum(array_column($details, 'total_deductions'));
$statusColors = [
    'PendingOwnerApproval' => '#f59e0b',
    'Approved'             => '#10b981',
    'Returned'             => '#ef4444',
];
$sc = $statusColors[$run['status']] ?? '#6b7280';
?>
<div class="page-head">
    <div>
        <h1>Payroll Review — <?= htmlspecialchars($run['branch_name']) ?></h1>
        <p><?= htmlspecialchars($run['period_label']) ?> &nbsp;|&nbsp; <a href="<?= $base ?>/owner/payroll">← Back to Approvals</a></p>
    </div>
    <span style="background:<?= $sc ?>;color:#fff;padding:4px 14px;border-radius:9999px;font-size:.875rem;align-self:center"><?= htmlspecialchars($run['status'] === 'PendingOwnerApproval' ? 'Pending Your Approval' : $run['status']) ?></span>
</div>

<!-- Summary strip -->
<div style="display:flex;flex-wrap:wrap;gap:2rem;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem 1.5rem;margin-bottom:1.25rem">
    <div><div style="font-size:.7rem;color:#6b7280;text-transform:uppercase">Employees</div><div style="font-weight:700;font-size:1.1rem"><?= count($details) ?></div></div>
    <div><div style="font-size:.7rem;color:#6b7280;text-transform:uppercase">Gross Total</div><div style="font-weight:700;font-size:1.1rem">₱<?= number_format($grossTotal, 2) ?></div></div>
    <div><div style="font-size:.7rem;color:#6b7280;text-transform:uppercase">Total Deductions</div><div style="font-weight:700;font-size:1.1rem;color:#ef4444">₱<?= number_format($dedTotal, 2) ?></div></div>
    <div><div style="font-size:.7rem;color:#6b7280;text-transform:uppercase">Net Total</div><div style="font-weight:700;font-size:1.3rem;color:#111827">₱<?= number_format($netTotal, 2) ?></div></div>
    <div><div style="font-size:.7rem;color:#6b7280;text-transform:uppercase">Submitted</div><div style="font-weight:600"><?= htmlspecialchars((string)($run['submitted_at'] ?? '—')) ?></div></div>
</div>

<!-- Employee breakdown -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem">
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th style="text-align:right">Gross Pay</th>
                <th style="text-align:right">Total Deductions</th>
                <th style="text-align:right;font-weight:700">Net Pay</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($details === []): ?>
            <tr><td colspan="4" style="text-align:center;color:#6b7280;padding:2rem">No employee records found.</td></tr>
        <?php else: ?>
            <?php foreach ($details as $d): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($d['employee_name']) ?></strong><br>
                    <small style="color:#6b7280"><?= htmlspecialchars($d['employee_number']) ?></small>
                </td>
                <td style="text-align:right">₱<?= number_format((float)$d['gross_pay'], 2) ?></td>
                <td style="text-align:right;color:#ef4444">₱<?= number_format((float)$d['total_deductions'], 2) ?></td>
                <td style="text-align:right;font-weight:700">₱<?= number_format((float)$d['net_pay'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:700">
                <td style="padding:.6rem .85rem">Total</td>
                <td style="text-align:right;padding:.6rem .85rem">₱<?= number_format($grossTotal, 2) ?></td>
                <td style="text-align:right;padding:.6rem .85rem;color:#ef4444">₱<?= number_format($dedTotal, 2) ?></td>
                <td style="text-align:right;padding:.6rem .85rem">₱<?= number_format($netTotal, 2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Actions -->
<?php if ($isPending): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;max-width:700px">

    <!-- Approve -->
    <div class="card">
        <h3 style="margin:0 0 .75rem;color:#166534">Approve Payroll</h3>
        <p style="font-size:.875rem;color:#374151;margin:0 0 1rem">Approving will finalize this run. This cannot be reversed.</p>
        <form method="post" action="<?= $base ?>/owner/payroll/<?= $runId ?>/approve"
              onsubmit="return confirm('Approve this payroll run? This action cannot be undone.')">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="btn btn-primary" style="width:100%;background:#16a34a">
                ✓ Approve Payroll
            </button>
        </form>
    </div>

    <!-- Return -->
    <div class="card">
        <h3 style="margin:0 0 .75rem;color:#991b1b">Return for Revision</h3>
        <form method="post" action="<?= $base ?>/owner/payroll/<?= $runId ?>/return">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <div class="form-group" style="margin-bottom:.75rem">
                <label for="return_reason" style="display:block;font-weight:500;margin-bottom:.25rem">
                    Return Reason <span style="color:#ef4444">*</span>
                </label>
                <textarea id="return_reason" name="return_reason" rows="4" class="form-control"
                          placeholder="Describe what needs correction…" required
                          style="width:100%;resize:vertical"></textarea>
                <?php if (isset($errors['return_reason'])): ?>
                <small style="color:#ef4444"><?= htmlspecialchars($errors['return_reason']) ?></small>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-secondary" style="width:100%;border-color:#ef4444;color:#ef4444"
                    onclick="return confirm('Return this payroll run to HR for revision?')">
                ↩ Return to HR
            </button>
        </form>
    </div>

</div>
<?php elseif ($run['status'] === 'Approved'): ?>
<p style="color:#10b981;font-weight:600;font-size:1rem">✓ This payroll run has been approved and is now read-only.</p>
<?php elseif ($run['status'] === 'Returned'): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:.75rem 1rem;color:#991b1b">
    <strong>Returned with reason:</strong> <?= htmlspecialchars((string)$run['return_reason']) ?>
</div>
<?php endif; ?>
