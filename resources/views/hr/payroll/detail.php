<?php
/**
 * View: hr/payroll/detail  (GET /hr/payroll/{id})
 * Variables: $run (from PayrollService::findRunOrFail()), $details (from runDetails()), $errors
 */
$errors  ??= [];
$details ??= [];

$statusColors = [
    'Draft'                => '#6b7280',
    'Computed'             => '#3b82f6',
    'PendingOwnerApproval' => '#f59e0b',
    'Approved'             => '#10b981',
    'Returned'             => '#ef4444',
];
$sc = $statusColors[$run['status']] ?? '#6b7280';

$grossTotal = array_sum(array_column($details, 'gross_pay'));
$dedTotal   = array_sum(array_column($details, 'total_deductions'));
$netTotal   = array_sum(array_column($details, 'net_pay'));
?>
<div class="page-head">
    <div>
        <h1>Payroll Run — <?= htmlspecialchars($run['branch_name']) ?></h1>
        <p><?= htmlspecialchars($run['period_label']) ?> &nbsp;|&nbsp; <a href="<?= $base ?>/hr/payroll">← Back to Payroll</a></p>
    </div>
    <span style="background:<?= $sc ?>;color:#fff;padding:4px 14px;border-radius:9999px;font-size:.875rem;align-self:center"><?= htmlspecialchars($run['status']) ?></span>
</div>

<?php if (!empty($run['return_reason'])): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b">
    <strong>Returned by Owner:</strong> <?= htmlspecialchars((string)$run['return_reason']) ?>
</div>
<?php endif; ?>

<!-- Meta strip -->
<div style="display:flex;flex-wrap:wrap;gap:2rem;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem 1.5rem;margin-bottom:1.25rem">
    <?php
    $meta = [
        'Branch'    => $run['branch_name'],
        'Period'    => $run['period_label'],
        'Created'   => $run['created_at'] ?? '',
        'Submitted' => $run['submitted_at'] ?? '—',
        'Reviewed'  => $run['reviewed_at']  ?? '—',
    ];
    foreach ($meta as $label => $val):
    ?>
    <div>
        <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase"><?= htmlspecialchars($label) ?></div>
        <div style="font-weight:600;margin-top:.15rem"><?= htmlspecialchars((string)$val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Employee rows -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem">
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th style="text-align:right">Daily Rate</th>
                <th style="text-align:right">Gross Pay</th>
                <th style="text-align:right">Deductions</th>
                <th style="text-align:right;font-weight:700">Net Pay</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($details === []): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b7280;padding:2rem">
                No employees computed yet. Use Compute below.
            </td></tr>
        <?php else: ?>
            <?php foreach ($details as $d): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($d['employee_name']) ?></strong><br>
                    <small style="color:#6b7280"><?= htmlspecialchars($d['employee_number']) ?></small>
                </td>
                <td style="text-align:right">₱<?= number_format((float)$d['daily_rate_snapshot'], 2) ?></td>
                <td style="text-align:right">₱<?= number_format((float)$d['gross_pay'], 2) ?></td>
                <td style="text-align:right;color:#ef4444">₱<?= number_format((float)$d['total_deductions'], 2) ?></td>
                <td style="text-align:right;font-weight:700">₱<?= number_format((float)$d['net_pay'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <?php if ($details !== []): ?>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:700">
                <td style="padding:.6rem .85rem" colspan="2">Totals</td>
                <td style="text-align:right;padding:.6rem .85rem">₱<?= number_format($grossTotal, 2) ?></td>
                <td style="text-align:right;padding:.6rem .85rem;color:#ef4444">₱<?= number_format($dedTotal, 2) ?></td>
                <td style="text-align:right;padding:.6rem .85rem">₱<?= number_format($netTotal, 2) ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>

<!-- Action buttons -->
<?php $runId = (int)$run['payroll_run_id']; ?>
<?php if (!in_array($run['status'], ['PendingOwnerApproval','Approved'], true)): ?>
<div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <!-- Compute -->
    <form method="post" action="<?= $base ?>/hr/payroll/<?= $runId ?>/compute">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn btn-secondary"
                onclick="return confirm('Run payroll computation for this period/branch?')">
            ⟳ Compute Payroll
        </button>
    </form>

    <!-- Submit for approval (only when Computed or Returned) -->
    <?php if (in_array($run['status'], ['Computed','Returned'], true)): ?>
    <form method="post" action="<?= $base ?>/hr/payroll/<?= $runId ?>/submit">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('Submit this payroll run to the Business Owner for approval?')">
            ↑ Submit for Approval
        </button>
    </form>
    <?php endif; ?>
</div>
<?php elseif ($run['status'] === 'Approved'): ?>
<p style="color:#10b981;font-weight:600">✓ This payroll run has been approved and is read-only.</p>
<?php else: ?>
<p style="color:#f59e0b;font-weight:600">⏳ Awaiting Business Owner approval.</p>
<?php endif; ?>
