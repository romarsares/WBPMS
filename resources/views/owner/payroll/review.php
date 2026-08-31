<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Business Owner — Payroll run review: itemized detail + approve/return actions.
 *
 * @var array{
 *     id: int,
 *     branch_name: string,
 *     period_label: string,
 *     status: string,
 *     employee_count: int,
 *     gross_total: string,
 *     net_total: string,
 *     submitted_at: string,
 *     return_reason: string|null
 * } $run
 * @var list<array{
 *     employee_name: string,
 *     employee_number: string,
 *     gross_pay: string,
 *     total_deductions: string,
 *     net_pay: string
 * }> $details
 * @var array<string,string> $errors   Validation errors from a return attempt
 * @var string               $csrf
 */

$run     ??= [];
$details ??= [];
$errors  ??= [];
$csrf    ??= '';

$isPending = ($run['status'] ?? '') === 'PendingOwnerApproval';
$isApproved = ($run['status'] ?? '') === 'Approved';
$isReturned = ($run['status'] ?? '') === 'Returned';

$statusBadge = match ($run['status'] ?? '') {
    'PendingOwnerApproval' => '<span class="badge badge-yellow">Pending Your Approval</span>',
    'Approved'             => '<span class="badge badge-green">Approved</span>',
    'Returned'             => '<span class="badge badge-red">Returned</span>',
    default                => '<span class="badge badge-gray">' . Formatter::escape($run['status'] ?? '') . '</span>',
};
?>

<div class="page-header">
    <h1>Payroll Review — <?= Formatter::escape($run['branch_name'] ?? '') ?></h1>
    <a href="/owner/payroll" class="btn btn-secondary">← Back to approvals</a>
</div>

<!-- Run summary -->
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
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Employees</div>
        <div style="font-weight:600"><?= (int) ($run['employee_count'] ?? 0) ?></div>
    </div>
    <div>
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Gross Total</div>
        <div style="font-weight:600">₱<?= Formatter::escape($run['gross_total'] ?? '0.00') ?></div>
    </div>
    <div>
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Net Total</div>
        <div style="font-size:1.25rem;font-weight:700;color:#111827">
            ₱<?= Formatter::escape($run['net_total'] ?? '0.00') ?>
        </div>
    </div>
    <div>
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Status</div>
        <div><?= $statusBadge ?></div>
    </div>
    <div>
        <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;margin-bottom:.25rem">Submitted</div>
        <div><?= Formatter::date($run['submitted_at'] ?? '') ?></div>
    </div>
</div>

<?php if ($isReturned && !empty($run['return_reason'])): ?>
<div class="flash flash-error">
    <strong>Previously returned with reason:</strong> <?= Formatter::escape($run['return_reason']) ?>
</div>
<?php endif; ?>

<!-- Employee breakdown -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem">
    <div style="padding:.85rem 1.25rem;border-bottom:1px solid #e5e7eb">
        <strong style="font-size:.95rem">Employee Breakdown</strong>
    </div>
    <?php if (empty($details)): ?>
    <p style="padding:1.25rem;color:#6b7280;font-size:.875rem;margin:0">No detail records.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th style="text-align:right">Gross Pay</th>
                <th style="text-align:right">Total Deductions</th>
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
            <td style="text-align:right">₱<?= Formatter::escape($row['gross_pay']) ?></td>
            <td style="text-align:right;color:#dc2626">₱<?= Formatter::escape($row['total_deductions']) ?></td>
            <td style="text-align:right;font-weight:600">₱<?= Formatter::escape($row['net_pay']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:700">
                <td style="padding:.65rem .85rem">Total</td>
                <td style="text-align:right;padding:.65rem .85rem">₱<?= Formatter::escape($run['gross_total'] ?? '') ?></td>
                <td></td>
                <td style="text-align:right;padding:.65rem .85rem">₱<?= Formatter::escape($run['net_total'] ?? '') ?></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>

<!-- Approval actions (only shown when pending) -->
<?php if ($isPending): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;max-width:740px">

    <!-- Approve -->
    <div class="card">
        <h2 style="margin-top:0;font-size:.95rem;color:#166534">Approve Payroll</h2>
        <p style="font-size:.875rem;color:#374151;margin:.25rem 0 1rem">
            Approving will finalize this payroll run. This action cannot be reversed.
        </p>
        <form method="POST"
              action="/owner/payroll/<?= (int) $run['id'] ?>/approve"
              onsubmit="return confirm('Approve this payroll run for ₱<?= Formatter::escape($run['net_total'] ?? '') ?> net pay? This cannot be undone.')">
            <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
            <button type="submit" class="btn btn-success" style="width:100%">
                ✓ Approve Payroll
            </button>
        </form>
    </div>

    <!-- Return -->
    <div class="card">
        <h2 style="margin-top:0;font-size:.95rem;color:#991b1b">Return for Revision</h2>
        <form method="POST" action="/owner/payroll/<?= (int) $run['id'] ?>/return">
            <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
            <div class="form-group">
                <label for="return_reason">
                    Return reason <span style="color:#dc2626">*</span>
                </label>
                <textarea id="return_reason" name="return_reason" rows="4"
                    placeholder="Describe what needs to be corrected..."
                    class="<?= isset($errors['return_reason']) ? 'is-invalid' : '' ?>"
                    required><?= Formatter::escape((string) ($_POST['return_reason'] ?? '')) ?></textarea>
                <?php if (isset($errors['return_reason'])): ?>
                <span class="field-error"><?= Formatter::escape($errors['return_reason']) ?></span>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-danger" style="width:100%">
                ↩ Return to HR
            </button>
        </form>
    </div>

</div>
<?php elseif ($isApproved): ?>
<div class="flash flash-success">
    This payroll run has been approved. It is now read-only.
</div>
<?php endif; ?>
