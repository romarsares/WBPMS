<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR — Payroll run detail.
 *
 * @var array{
 *     id: int,
 *     branch_name: string,
 *     period_label: string,
 *     status: string,
 *     created_at: string,
 *     submitted_at: string|null,
 *     return_reason: string|null
 * } $run
 * @var list<array{
 *     employee_id: int,
 *     employee_number: string,
 *     employee_name: string,
 *     daily_rate: string,
 *     days_present: int,
 *     gross_pay: string,
 *     sss: string,
 *     philhealth: string,
 *     pagibig: string,
 *     cash_advance: string,
 *     other_deductions: string,
 *     total_deductions: string,
 *     net_pay: string
 * }> $details
 * @var string $grossTotal
 * @var string $totalDeductions
 * @var string $netTotal
 * @var string $csrf
 */

$run            ??= [];
$details        ??= [];
$grossTotal     ??= '0.00';
$totalDeductions ??= '0.00';
$netTotal       ??= '0.00';
$csrf           ??= '';

$canSubmit = ($run['status'] ?? '') === 'Computed';
$isLocked  = in_array($run['status'] ?? '', ['PendingOwnerApproval', 'Approved'], true);

$statusBadge = match ($run['status'] ?? '') {
    'Draft'                => '<span class="badge badge-gray">Draft</span>',
    'Computed'             => '<span class="badge badge-blue">Computed</span>',
    'PendingOwnerApproval' => '<span class="badge badge-yellow">Pending Owner Approval</span>',
    'Approved'             => '<span class="badge badge-green">Approved</span>',
    'Returned'             => '<span class="badge badge-red">Returned</span>',
    default                => '<span class="badge badge-gray">' . Formatter::escape($run['status'] ?? '') . '</span>',
};
?>

<div class="page-header">
    <h1>Payroll Run — <?= Formatter::escape($run['branch_name'] ?? '') ?></h1>
    <a href="/hr/payroll" class="btn btn-secondary">← Back to payroll</a>
</div>

<!-- Run meta -->
<div class="card" style="display:flex;flex-wrap:wrap;gap:2rem;padding:1rem 1.5rem;margin-bottom:1.25rem">
    <div>
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Branch</div>
        <div style="font-weight:600"><?= Formatter::escape($run['branch_name'] ?? '') ?></div>
    </div>
    <div>
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Period</div>
        <div style="font-weight:600"><?= Formatter::escape($run['period_label'] ?? '') ?></div>
    </div>
    <div>
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Status</div>
        <div><?= $statusBadge ?></div>
    </div>
    <div>
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Created</div>
        <div><?= Formatter::date($run['created_at'] ?? '') ?></div>
    </div>
    <?php if (!empty($run['submitted_at'])): ?>
    <div>
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Submitted</div>
        <div><?= Formatter::date($run['submitted_at']) ?></div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($run['return_reason'])): ?>
<div class="flash flash-error" role="alert">
    <strong>Returned by Owner:</strong> <?= Formatter::escape($run['return_reason']) ?>
</div>
<?php endif; ?>

<!-- Detail table -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem">
    <div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th style="text-align:right">Daily Rate</th>
                <th style="text-align:center">Days</th>
                <th style="text-align:right">Gross</th>
                <th style="text-align:right">SSS</th>
                <th style="text-align:right">PhilHealth</th>
                <th style="text-align:right">Pag-IBIG</th>
                <th style="text-align:right">Cash Adv.</th>
                <th style="text-align:right">Other</th>
                <th style="text-align:right">Total Ded.</th>
                <th style="text-align:right;font-weight:700">Net Pay</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($details as $row): ?>
        <tr>
            <td>
                <div style="font-weight:500"><?= Formatter::escape($row['employee_name']) ?></div>
                <div style="font-size:.78rem;color:#6b7280"><?= Formatter::escape($row['employee_number']) ?></div>
            </td>
            <td style="text-align:right">₱<?= Formatter::escape($row['daily_rate']) ?></td>
            <td style="text-align:center"><?= (int) $row['days_present'] ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($row['gross_pay']) ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($row['sss']) ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($row['philhealth']) ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($row['pagibig']) ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($row['cash_advance']) ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($row['other_deductions']) ?></td>
            <td style="text-align:right;color:#dc2626">₱<?= Formatter::escape($row['total_deductions']) ?></td>
            <td style="text-align:right;font-weight:700">₱<?= Formatter::escape($row['net_pay']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:700">
                <td colspan="3" style="padding:.65rem .85rem">Totals</td>
                <td style="text-align:right;padding:.65rem .85rem">₱<?= Formatter::escape($grossTotal) ?></td>
                <td colspan="5"></td>
                <td style="text-align:right;padding:.65rem .85rem;color:#dc2626">₱<?= Formatter::escape($totalDeductions) ?></td>
                <td style="text-align:right;padding:.65rem .85rem">₱<?= Formatter::escape($netTotal) ?></td>
            </tr>
        </tfoot>
    </table>
    </div>
</div>

<!-- Actions -->
<?php if (!$isLocked): ?>
<div style="display:flex;gap:.75rem;align-items:center">
    <?php if ($canSubmit): ?>
    <form method="POST" action="/hr/payroll/<?= (int) $run['id'] ?>/submit"
          onsubmit="return confirm('Submit this payroll run for Owner approval? This cannot be undone.')">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
        <button type="submit" class="btn btn-primary">Submit for Owner Approval</button>
    </form>
    <?php else: ?>
    <span style="font-size:.875rem;color:#6b7280">
        Status is <strong><?= Formatter::escape($run['status'] ?? '') ?></strong> — no actions available.
    </span>
    <?php endif; ?>
</div>
<?php else: ?>
<p style="font-size:.875rem;color:#6b7280">
    This payroll run has been submitted and is now read-only.
</p>
<?php endif; ?>
