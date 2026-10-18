<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * View: hr/reports/payslips  (GET /hr/reports/payslips)
 *
 * @var list<array{payslip_id:int,employee_number:string,employee_name:string,branch_name:string,period_label:string,period_start:string,period_end:string,gross_pay:float,total_deductions:float,net_pay:float,approved_at:string}> $rows
 * @var list<array{branch_id:int,branch_name:string}> $branches
 * @var list<array{payroll_period_id:int,period_start:string,period_end:string}> $periods
 * @var array{branch_id:int,payroll_period_id:int,employee_search:string} $filters
 */

$rows     ??= [];
$branches ??= [];
$periods  ??= [];
$filters  ??= [];

$exportQuery = http_build_query(array_filter([
    'type'               => 'payslips',
    'branch_id'          => $filters['branch_id'] ?? 0,
    'payroll_period_id'  => $filters['payroll_period_id'] ?? 0,
    'employee_search'    => $filters['employee_search'] ?? '',
], static fn(mixed $v): bool => $v !== '' && $v !== 0));
?>

<div class="page-header">
    <div>
        <h1>Employee Payslip Report</h1>
        <p>View and export employee payslips by period, branch, or employee.</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <a href="<?= $base ?>/hr/reports" class="btn btn-secondary">← Back</a>
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

<!-- Results table -->
<div class="card" style="padding:0;overflow:hidden">
    <?php if ($rows === []): ?>
    <p style="padding:1.5rem;color:#6b7280;margin:0">
        Select a payroll period, branch, or employee above and click <strong>Filter</strong> to view payslips.
    </p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee #</th>
                <th>Employee Name</th>
                <th>Branch</th>
                <th>Period</th>
                <th style="text-align:right">Gross Pay</th>
                <th style="text-align:right">Deductions</th>
                <th style="text-align:right">Net Pay</th>
                <th>Approved At</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $totalGross = 0.0;
        $totalDeductions = 0.0;
        $totalNet = 0.0;
        foreach ($rows as $r):
            $totalGross      += (float) $r['gross_pay'];
            $totalDeductions += (float) $r['total_deductions'];
            $totalNet        += (float) $r['net_pay'];
        ?>
        <tr>
            <td><?= Formatter::escape($r['employee_number']) ?></td>
            <td style="font-weight:500"><?= Formatter::escape($r['employee_name']) ?></td>
            <td><?= Formatter::escape($r['branch_name']) ?></td>
            <td style="white-space:nowrap"><?= Formatter::escape($r['period_label']) ?></td>
            <td style="text-align:right">₱<?= number_format((float) $r['gross_pay'], 2) ?></td>
            <td style="text-align:right">₱<?= number_format((float) $r['total_deductions'], 2) ?></td>
            <td style="text-align:right;font-weight:600">₱<?= number_format((float) $r['net_pay'], 2) ?></td>
            <td style="font-size:.8rem;color:#6b7280"><?= Formatter::escape((string) ($r['approved_at'] ?? '—')) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f8fafc;font-weight:600">
                <td colspan="4" style="padding:.6rem 1rem;text-align:right;color:#374151">
                    Total (<?= count($rows) ?> record<?= count($rows) !== 1 ? 's' : '' ?>)
                </td>
                <td style="text-align:right;padding:.6rem .75rem">₱<?= number_format($totalGross, 2) ?></td>
                <td style="text-align:right;padding:.6rem .75rem">₱<?= number_format($totalDeductions, 2) ?></td>
                <td style="text-align:right;padding:.6rem .75rem">₱<?= number_format($totalNet, 2) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>
