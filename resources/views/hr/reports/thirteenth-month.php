<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * View: hr/reports/thirteenth-month (GET /hr/reports/13th-month)
 *
 * @var list<array{employee_id:int,employee_name:string,employee_number:string,basic_total:float,thirteenth_month:float}> $rows
 * @var list<array{year:string|int}> $years
 * @var list<array{branch_id:string|int,branch_name:string}> $branches
 * @var list<array{employee_id:string|int,employee_number:string,employee_name:string}> $employees
 * @var array{year:int,branch_id:int,employee_id:int} $filters
 */

$rows ??= [];
$years ??= [];
$branches ??= [];
$employees ??= [];
$filters ??= ['year' => (int) date('Y'), 'branch_id' => 0, 'employee_id' => 0];

$query = http_build_query([
    'type' => '13th_month',
    'year' => $filters['year'],
    'branch_id' => $filters['branch_id'] ?: null,
    'employee_id' => $filters['employee_id'] ?: null,
]);
$totalBasicPay = array_sum(array_column($rows, 'basic_total'));
$totalThirteenthMonth = array_sum(array_column($rows, 'thirteenth_month'));
?>

<div class="page-header">
    <div>
        <h1>13th Month Pay Report</h1>
        <p>Annual basic-pay and 13th-month-pay computation from approved payroll runs.</p>
    </div>
    <a href="<?= $base ?>/hr/reports/export?<?= Formatter::escape($query) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Print Report</a>
</div>

<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/hr/reports/13th-month" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem" for="year">Year</label>
            <select id="year" name="year" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <?php foreach ($years as $availableYear): ?>
                <option value="<?= (int) $availableYear['year'] ?>" <?= (int) $availableYear['year'] === $filters['year'] ? 'selected' : '' ?>><?= (int) $availableYear['year'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem" for="branch-id">Branch</label>
            <select id="branch-id" name="branch_id" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All branches</option>
                <?php foreach ($branches as $branch): ?>
                <option value="<?= (int) $branch['branch_id'] ?>" <?= (int) $branch['branch_id'] === $filters['branch_id'] ? 'selected' : '' ?>><?= Formatter::escape($branch['branch_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem" for="employee-id">Employee</label>
            <select id="employee-id" name="employee_id" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;max-width:280px">
                <option value="">All employees</option>
                <?php foreach ($employees as $employee): ?>
                <option value="<?= (int) $employee['employee_id'] ?>" <?= (int) $employee['employee_id'] === $filters['employee_id'] ? 'selected' : '' ?>><?= Formatter::escape($employee['employee_number'] . ' — ' . $employee['employee_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filters['branch_id'] !== 0 || $filters['employee_id'] !== 0 || $filters['year'] !== (int) date('Y')): ?>
        <a href="<?= $base ?>/hr/reports/13th-month" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="stats-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.25rem">
    <div class="stat-card"><span class="stat-value"><?= count($rows) ?></span><span class="stat-label">Employees</span></div>
    <div class="stat-card"><span class="stat-value">₱<?= number_format($totalBasicPay, 2) ?></span><span class="stat-label">Total Basic Pay</span></div>
    <div class="stat-card"><span class="stat-value">₱<?= number_format($totalThirteenthMonth, 2) ?></span><span class="stat-label">Total 13th Month Pay</span></div>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <?php if ($rows === []): ?>
    <p style="padding:1.5rem;color:#6b7280;font-size:.875rem;margin:0">No approved basic-pay earnings match the current filters.</p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee No.</th>
                <th>Employee Name</th>
                <th style="text-align:right">Total Basic Pay</th>
                <th style="text-align:right">13th Month Pay</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= Formatter::escape($row['employee_number']) ?></td>
                <td><?= Formatter::escape($row['employee_name']) ?></td>
                <td style="text-align:right">₱<?= number_format($row['basic_total'], 2) ?></td>
                <td style="text-align:right;font-weight:600">₱<?= number_format($row['thirteenth_month'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">Total</th>
                <th style="text-align:right">₱<?= number_format($totalBasicPay, 2) ?></th>
                <th style="text-align:right">₱<?= number_format($totalThirteenthMonth, 2) ?></th>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>