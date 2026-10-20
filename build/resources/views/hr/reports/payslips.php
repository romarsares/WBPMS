<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * View: hr/reports/payslips  (GET /hr/reports/payslips)
 *
 * Displays a detailed payslip card per employee — earnings and deductions
 * breakdown, gross, total deductions, and net pay — so HR/Owner can review
 * each employee's payslip on-screen before printing or exporting.
 *
 * @var list<array<string,mixed>> $payslips  Each entry is the array returned by
 *                                         PayrollService::payslipData(), with
 *                                         an additional 'approved_at' key.
 * @var list<array{branch_id:int,branch_name:string}> $branches
 * @var list<array{payroll_period_id:int,period_start:string,period_end:string}> $periods
 * @var array{branch_id:int,payroll_period_id:int,employee_search:string} $filters
 */

$payslips  ??= [];
$branches  ??= [];
$periods   ??= [];
$filters   ??= [];

$exportQuery = http_build_query(array_filter([
    'type'               => 'payslips',
    'branch_id'          => $filters['branch_id'] ?? 0,
    'payroll_period_id'  => $filters['payroll_period_id'] ?? 0,
    'employee_search'    => $filters['employee_search'] ?? '',
], static fn(mixed $v): bool => $v !== '' && $v !== 0));

$bulkQuery = http_build_query(array_filter([
    'branch_id'         => $filters['branch_id'] ?? 0,
    'payroll_period_id' => $filters['payroll_period_id'] ?? 0,
    'employee_search'   => $filters['employee_search'] ?? '',
], static fn(mixed $v): bool => $v !== '' && $v !== 0));

// Report-level totals across all matched payslips
$totalGross = 0.0;
$totalDeductions = 0.0;
$totalNet = 0.0;
foreach ($payslips as $ps) {
    $totalGross      += (float) ($ps['gross_pay'] ?? 0);
    $totalDeductions += (float) ($ps['total_deductions'] ?? 0);
    $totalNet        += (float) ($ps['net_pay'] ?? 0);
}
?>

<div class="page-header">
    <div>
        <h1>Employee Payslip Report</h1>
        <p>Detailed payslip per employee — earnings, deductions, and net pay.</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <a href="<?= $base ?>/hr/reports" class="btn btn-secondary">← Back</a>
        <a href="<?= $base ?>/hr/reports/payslips/bulk<?= $bulkQuery !== '' ? '?' . Formatter::escape($bulkQuery) : '' ?>"
           class="btn btn-secondary" target="_blank" rel="noopener">🖨 Print 1/4 Sheet</a>
        <a href="<?= $base ?>/hr/reports/export?<?= Formatter::escape($exportQuery) ?>"
           class="btn btn-secondary" target="_blank" rel="noopener">🖨 Print</a>
        <a href="<?= $base ?>/hr/reports/export?<?= Formatter::escape($exportQuery) ?>&amp;format=pdf"
           class="btn btn-secondary" target="_blank" rel="noopener">⬇ PDF</a>
        <a href="<?= $base ?>/hr/reports/export?<?= Formatter::escape($exportQuery) ?>&amp;format=csv"
           class="btn btn-primary">⬇ CSV</a>
    </div>
</div>

<!-- Filter bar -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/hr/reports/payslips"
          style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">

        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Payroll Period</label>
            <select name="payroll_period_id" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;min-width:220px">
                <option value="">All periods</option>
                <?php foreach ($periods as $p): ?>
                <option value="<?= (int) $p['payroll_period_id'] ?>"
                    <?= (int) $p['payroll_period_id'] === (int) ($filters['payroll_period_id'] ?? 0) ? 'selected' : '' ?>>
                    <?= Formatter::escape($p['period_start'] . ' – ' . $p['period_end']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Branch</label>
            <select name="branch_id" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;min-width:160px">
                <option value="">All branches</option>
                <?php foreach ($branches as $branch): ?>
                <option value="<?= (int) $branch['branch_id'] ?>"
                    <?= (int) $branch['branch_id'] === (int) ($filters['branch_id'] ?? 0) ? 'selected' : '' ?>>
                    <?= Formatter::escape($branch['branch_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Employee</label>
            <input type="search"
                   name="employee_search"
                   value="<?= Formatter::escape((string) ($filters['employee_search'] ?? '')) ?>"
                   placeholder="Name or employee no."
                   style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;width:180px">
        </div>

        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= $base ?>/hr/reports/payslips" class="btn btn-secondary">Clear</a>
    </form>
</div>

<?php if ($payslips === []): ?>
<div class="card" style="padding:1.5rem;color:#6b7280">
    No approved payslips found. Run and approve a payroll first, or adjust the filters above.
</div>
<?php else: ?>

<!-- Report totals summary -->
<div class="cards three" style="margin-bottom:1.25rem">
    <div class="stat">
        <strong><?= count($payslips) ?></strong>
        <span>Payslips</span>
    </div>
    <div class="stat">
        <strong>₱<?= number_format($totalGross, 2) ?></strong>
        <span>Total Gross Pay</span>
    </div>
    <div class="stat">
        <strong>₱<?= number_format($totalNet, 2) ?></strong>
        <span>Total Net Pay</span>
    </div>
</div>

<!-- Per-employee payslip cards -->
<div class="payslip-report-list">
<?php foreach ($payslips as $ps): ?>
    <?php
    $empName    = (string) ($ps['employee_name']   ?? '');
    $empNumber  = (string) ($ps['employee_number'] ?? '');
    $position   = (string) ($ps['position']       ?? '—');
    $branchName = (string) ($ps['branch_name']    ?? '');
    $period     = (string) ($ps['period']          ?? '');
    $payDate    = (string) ($ps['pay_date']        ?? '');
    $dailyRate  = (float)  ($ps['daily_rate']      ?? 0);
    $gross      = (float)  ($ps['gross_pay']       ?? 0);
    $deductions = (float)  ($ps['total_deductions'] ?? 0);
    $net        = (float)  ($ps['net_pay']         ?? 0);
    $earnings   = is_array($ps['earnings']   ?? null) ? $ps['earnings']   : [];
    $dedRows    = is_array($ps['deductions'] ?? null) ? $ps['deductions'] : [];
    ?>
    <div class="card payslip-card" style="padding:1.25rem;margin-bottom:1.25rem;page-break-inside:avoid">
        <!-- Card header -->
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;border-bottom:2px solid #e5e7eb;padding-bottom:.75rem;margin-bottom:1rem">
            <div>
                <div style="font-weight:700;font-size:1.05rem"><?= Formatter::escape($empName) ?></div>
                <div style="font-size:.82rem;color:#6b7280;margin-top:.15rem">
                    <?= Formatter::escape($empNumber) ?> &middot; <?= Formatter::escape($position) ?>
                </div>
            </div>
            <div style="text-align:right">
                <div style="font-weight:600"><?= Formatter::escape($period) ?></div>
                <div style="font-size:.8rem;color:#6b7280">
                    Branch: <?= Formatter::escape($branchName) ?>
                    <?php if ($payDate !== ''): ?> &middot; Pay date: <?= Formatter::escape($payDate) ?><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Earnings + Deductions side-by-side -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <!-- Earnings -->
            <div>
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:.4rem .25rem;font-size:.78rem;text-transform:uppercase;color:#166534">Earnings</th>
                            <th style="text-align:right;border-bottom:1px solid #e5e7eb;padding:.4rem .25rem;font-size:.78rem;text-transform:uppercase;color:#166534">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($earnings as $row): ?>
                        <tr>
                            <td style="padding:.4rem .25rem;font-size:.85rem;border-bottom:1px solid #f1f5f9"><?= Formatter::escape((string) ($row['description'] ?? '')) ?></td>
                            <td style="padding:.4rem .25rem;font-size:.85rem;text-align:right;border-bottom:1px solid #f1f5f9;color:#15803d">₱<?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($earnings === []): ?>
                        <tr><td colspan="2" style="padding:.6rem .25rem;font-size:.85rem;color:#9ca3af">No earnings recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f0fdf4">
                            <td style="padding:.5rem .25rem;font-weight:700;font-size:.85rem">Gross Pay</td>
                            <td style="padding:.5rem .25rem;font-weight:700;text-align:right;font-size:.95rem;color:#15803d">₱<?= number_format($gross, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Deductions -->
            <div>
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:.4rem .25rem;font-size:.78rem;text-transform:uppercase;color:#991b1b">Deductions</th>
                            <th style="text-align:right;border-bottom:1px solid #e5e7eb;padding:.4rem .25rem;font-size:.78rem;text-transform:uppercase;color:#991b1b">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dedRows as $row): ?>
                        <tr>
                            <td style="padding:.4rem .25rem;font-size:.85rem;border-bottom:1px solid #f1f5f9"><?= Formatter::escape((string) ($row['description'] ?? '')) ?></td>
                            <td style="padding:.4rem .25rem;font-size:.85rem;text-align:right;border-bottom:1px solid #f1f5f9;color:#dc2626">₱<?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($dedRows === []): ?>
                        <tr><td colspan="2" style="padding:.6rem .25rem;font-size:.85rem;color:#9ca3af">No deductions recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#fef2f2">
                            <td style="padding:.5rem .25rem;font-weight:700;font-size:.85rem">Total Deductions</td>
                            <td style="padding:.5rem .25rem;font-weight:700;text-align:right;font-size:.95rem;color:#dc2626">₱<?= number_format($deductions, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Net pay banner -->
        <div style="margin-top:1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.85rem 1rem;display:flex;justify-content:space-between;align-items:center">
            <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#1e40af;font-weight:600">Net Pay</div>
            <div style="font-size:1.35rem;font-weight:700;color:#1d4ed8">₱<?= number_format($net, 2) ?></div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Report footer totals -->
<div class="card" style="padding:1rem 1.25rem;background:#f8fafc;border-top:3px solid #1d4ed8;margin-bottom:1.25rem">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:1rem;align-items:center">
        <div style="font-weight:700">Report Totals (<?= count($payslips) ?> payslip<?= count($payslips) !== 1 ? 's' : '' ?>)</div>
        <div style="display:flex;gap:2rem;flex-wrap:wrap">
            <div><span style="font-size:.78rem;color:#6b7280">Total Gross</span> <strong>₱<?= number_format($totalGross, 2) ?></strong></div>
            <div><span style="font-size:.78rem;color:#6b7280">Total Deductions</span> <strong>₱<?= number_format($totalDeductions, 2) ?></strong></div>
            <div><span style="font-size:.78rem;color:#6b7280">Total Net Pay</span> <strong style="color:#1d4ed8">₱<?= number_format($totalNet, 2) ?></strong></div>
        </div>
    </div>
</div>
<?php endif; ?>
