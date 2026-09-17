<?php
/**
 * View: hr/payroll/detail  (GET /hr/payroll/{id})
 * Variables: $run, $details, $earningSummary, $earnings, $deductionSummary, $deductions, $errors
 */
$errors  ??= [];
$details ??= [];
$earningSummary ??= [];
$earnings ??= [];
$deductionSummary ??= [];
$deductions ??= [];

$statusColors = [
    'Draft'                => '#6b7280',
    'Computed'             => '#3b82f6',
    'PendingOwnerApproval' => '#f59e0b',
    'Approved'             => '#10b981',
    'Returned'             => '#ef4444',
    'Cancelled'            => '#6b7280',
];
$sc = $statusColors[$run['status']] ?? '#6b7280';

$grossTotal = array_sum(array_column($details, 'gross_pay'));
$dedTotal   = array_sum(array_column($details, 'total_deductions'));
$netTotal   = array_sum(array_column($details, 'net_pay'));
$earningsByPayroll = [];
foreach ($earnings as $earning) {
    $earningsByPayroll[(int) $earning['payroll_id']][] = $earning;
}
$deductionsByPayroll = [];
foreach ($deductions as $deduction) {
    $deductionsByPayroll[(int) $deduction['payroll_id']][] = $deduction;
}
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

<?php if (!empty($run['cancellation_reason'])): ?>
<div style="background:#f9fafb;border:1px solid #d1d5db;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;color:#374151">
    <strong>Cancelled by HR:</strong> <?= htmlspecialchars((string)$run['cancellation_reason']) ?>
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

<?php if ($earningSummary !== []): ?>
<!-- Earnings summary -->
<div class="card" style="padding:0;overflow-x:auto;margin-bottom:1.25rem">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--line)">
        <h2 style="margin:0;font-size:1.05rem">Earnings Breakdown</h2>
        <p style="margin:.25rem 0 0;color:#6b7280;font-size:.875rem">Basic pay, approved overtime matched to actual attendance, holiday premiums above Basic Pay, and other earnings included in gross pay.</p>
    </div>
    <table class="data-table">
        <thead><tr><th>Earning Type</th><th style="text-align:right">Employees / Records</th><th style="text-align:right">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($earningSummary as $summary): ?>
            <tr>
                <td><?= htmlspecialchars($summary['earning_type']) ?></td>
                <td style="text-align:right"><?= (int) $summary['record_count'] ?></td>
                <td style="text-align:right;color:#047857;font-weight:600">₱<?= number_format((float) $summary['total_amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr style="background:#f9fafb;font-weight:700"><td colspan="2" style="padding:.6rem .85rem">Total Gross Pay</td><td style="text-align:right;padding:.6rem .85rem;color:#047857">₱<?= number_format($grossTotal, 2) ?></td></tr></tfoot>
    </table>
</div>
<?php endif; ?>

<?php if ($deductionSummary !== []): ?>
<!-- Deduction summary -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--line)">
        <h2 style="margin:0;font-size:1.05rem">Deduction Breakdown</h2>
        <p style="margin:.25rem 0 0;color:#6b7280;font-size:.875rem">Totals posted by the computed payroll run.</p>
    </div>
    <table class="data-table">
        <thead><tr><th>Deduction Type</th><th style="text-align:right">Employees / Records</th><th style="text-align:right">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($deductionSummary as $summary): ?>
            <tr>
                <td><?= htmlspecialchars($summary['deduction_type']) ?></td>
                <td style="text-align:right"><?= (int) $summary['record_count'] ?></td>
                <td style="text-align:right;color:#ef4444;font-weight:600">₱<?= number_format((float) $summary['total_amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr style="background:#f9fafb;font-weight:700"><td colspan="2" style="padding:.6rem .85rem">Total Deductions</td><td style="text-align:right;padding:.6rem .85rem;color:#ef4444">₱<?= number_format($dedTotal, 2) ?></td></tr></tfoot>
    </table>
</div>
<?php endif; ?>

<!-- Employee rows -->
<div class="card" style="padding:0;overflow-x:auto;margin-bottom:1.25rem">
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th style="text-align:right">Daily Rate</th>
                <th>Earning Details</th>
                <th style="text-align:right">Gross Pay</th>
                <th style="text-align:right">Deductions</th>
                <th>Deduction Details</th>
                <th style="text-align:right;font-weight:700">Net Pay</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if ($details === []): ?>
            <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:2rem">
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
                <td>
                    <?php $employeeEarnings = $earningsByPayroll[(int) $d['payroll_id']] ?? []; ?>
                    <?php if ($employeeEarnings === []): ?>
                        <span style="color:#6b7280">—</span>
                    <?php else: ?>
                        <details>
                            <summary style="cursor:pointer;color:#047857">View <?= count($employeeEarnings) ?> item<?= count($employeeEarnings) === 1 ? '' : 's' ?></summary>
                            <ul style="margin:.5rem 0 0;padding-left:1rem;font-size:.82rem;min-width:180px">
                            <?php foreach ($employeeEarnings as $item): ?>
                                <li><?= htmlspecialchars($item['earning_type']) ?> — <strong>₱<?= number_format((float) $item['amount'], 2) ?></strong><br><small style="color:#6b7280"><?= htmlspecialchars($item['description']) ?></small></li>
                            <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </td>
                <td style="text-align:right">₱<?= number_format((float)$d['gross_pay'], 2) ?></td>
                <td style="text-align:right;color:#ef4444">₱<?= number_format((float)$d['total_deductions'], 2) ?></td>
                <td>
                    <?php $employeeDeductions = $deductionsByPayroll[(int) $d['payroll_id']] ?? []; ?>
                    <?php if ($employeeDeductions === []): ?>
                        <span style="color:#6b7280">—</span>
                    <?php else: ?>
                        <details>
                            <summary style="cursor:pointer;color:#1d4ed8">View <?= count($employeeDeductions) ?> item<?= count($employeeDeductions) === 1 ? '' : 's' ?></summary>
                            <ul style="margin:.5rem 0 0;padding-left:1rem;font-size:.82rem;min-width:180px">
                            <?php foreach ($employeeDeductions as $item): ?>
                                <li><?= htmlspecialchars($item['deduction_type']) ?> — <strong>₱<?= number_format((float) $item['amount'], 2) ?></strong><br><small style="color:#6b7280"><?= htmlspecialchars($item['description']) ?></small></li>
                            <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;font-weight:700">₱<?= number_format((float)$d['net_pay'], 2) ?></td>
                <td style="text-align:right">
                    <?php if (in_array($run['status'], ['Computed', 'Returned'], true)): ?>
                    <a href="<?= $base ?>/hr/payroll/<?= (int) $run['payroll_run_id'] ?>/employees/<?= (int) $d['payroll_id'] ?>/adjust"
                       class="btn btn-secondary" style="padding:.3rem .65rem;font-size:.8rem">Adjust</a>
                    <?php else: ?>
                    <span style="color:#6b7280">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <?php if ($details !== []): ?>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:700">
                <td style="padding:.6rem .85rem" colspan="2">Totals</td>
                <td></td>
                <td style="text-align:right;padding:.6rem .85rem">₱<?= number_format($grossTotal, 2) ?></td>
                <td style="text-align:right;padding:.6rem .85rem;color:#ef4444">₱<?= number_format($dedTotal, 2) ?></td>
                <td></td>
                <td style="text-align:right;padding:.6rem .85rem">₱<?= number_format($netTotal, 2) ?></td>
                <td></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>

<!-- Action buttons -->
<?php $runId = (int)$run['payroll_run_id']; ?>
<?php if (!in_array($run['status'], ['PendingOwnerApproval', 'Approved', 'Cancelled'], true)): ?>
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

    <form method="post" action="<?= $base ?>/hr/payroll/<?= $runId ?>/cancel" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="text" name="cancellation_reason" required maxlength="1000"
               aria-label="Cancellation reason" placeholder="Reason for cancellation">
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('Cancel this payroll run? It cannot be restored, but its history will be retained.')">
            Cancel Payroll Run
        </button>
    </form>
</div>
<?php elseif ($run['status'] === 'Approved'): ?>
<p style="color:#10b981;font-weight:600">✓ This payroll run has been approved and is read-only.</p>
<?php elseif ($run['status'] === 'Cancelled'): ?>
<p style="color:#6b7280;font-weight:600">⊘ This payroll run is cancelled and read-only. Create a new run when the correction is ready.</p>
<?php else: ?>
<div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
    <p style="color:#f59e0b;font-weight:600;margin:0">&#9711; Awaiting Business Owner approval.</p>
    <form method="post" action="<?= $base ?>/hr/payroll/<?= $runId ?>/cancel" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="text" name="cancellation_reason" required maxlength="1000"
               aria-label="Cancellation reason" placeholder="Reason for cancellation">
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('Cancel this pending payroll run? It cannot be restored, but its history will be retained.')">
            Cancel Payroll Run
        </button>
    </form>
</div>
<?php endif; ?>
