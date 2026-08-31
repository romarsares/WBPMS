<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR — Attendance timesheet review list.
 *
 * @var list<array{
 *     employee_id: int,
 *     employee_number: string,
 *     employee_name: string,
 *     branch_name: string,
 *     period: string,
 *     days_present: int,
 *     total_worked_minutes: int,
 *     total_late_minutes: int,
 *     total_undertime_minutes: int,
 *     total_overtime_minutes: int,
 *     incomplete_days: int,
 *     flags: list<string>
 * }> $rows
 * @var list<array{id:int,name:string}> $branches
 * @var list<array{id:int,label:string}> $periods    Available import batches/periods
 * @var string $filterBranch
 * @var string $filterPeriod
 * @var int    $unmatchedCount   Punches with no matched employee
 */

$rows           ??= [];
$branches       ??= [];
$periods        ??= [];
$filterBranch   ??= '';
$filterPeriod   ??= '';
$unmatchedCount ??= 0;

$fmt = static fn(int $minutes): string => sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
?>

<div class="page-header">
    <h1>Attendance Review</h1>
    <a href="/hr/attendance/import" class="btn btn-primary">Import Workbook</a>
</div>

<?php if ($unmatchedCount > 0): ?>
<div class="flash flash-warning" role="alert">
    ⚠ <?= $unmatchedCount ?> biometric punch<?= $unmatchedCount !== 1 ? 'es' : '' ?>
    could not be matched to an employee.
    <a href="/hr/attendance/unmatched">Review unmatched →</a>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="/hr/attendance"
          style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Branch</label>
            <select name="branch" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All branches</option>
                <?php foreach ($branches as $branch): ?>
                <option value="<?= (int) $branch['id'] ?>"
                    <?= ((string) $branch['id'] === $filterBranch) ? 'selected' : '' ?>>
                    <?= Formatter::escape($branch['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Period</label>
            <select name="period" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All periods</option>
                <?php foreach ($periods as $p): ?>
                <option value="<?= Formatter::escape((string) $p['id']) ?>"
                    <?= ((string) $p['id'] === $filterPeriod) ? 'selected' : '' ?>>
                    <?= Formatter::escape($p['label']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filterBranch !== '' || $filterPeriod !== ''): ?>
        <a href="/hr/attendance" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Timesheet table -->
<div class="card" style="padding:0;overflow:hidden">
    <?php if (empty($rows)): ?>
    <p style="padding:1.5rem;color:#6b7280;font-size:.875rem;margin:0">
        No attendance records found. Import a workbook to generate timesheets.
    </p>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Branch</th>
                <th>Period</th>
                <th style="text-align:center">Days Present</th>
                <th style="text-align:right">Worked</th>
                <th style="text-align:right">Late</th>
                <th style="text-align:right">Undertime</th>
                <th style="text-align:right">Overtime</th>
                <th style="text-align:center">Incomplete</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td>
                <div style="font-weight:500"><?= Formatter::escape($row['employee_name']) ?></div>
                <div style="font-size:.78rem;color:#6b7280"><?= Formatter::escape($row['employee_number']) ?></div>
            </td>
            <td><?= Formatter::escape($row['branch_name']) ?></td>
            <td><?= Formatter::escape($row['period']) ?></td>
            <td style="text-align:center"><?= (int) $row['days_present'] ?></td>
            <td style="text-align:right"><?= $fmt((int) $row['total_worked_minutes']) ?></td>
            <td style="text-align:right;<?= (int) $row['total_late_minutes'] > 0 ? 'color:#b45309' : '' ?>">
                <?= $fmt((int) $row['total_late_minutes']) ?>
            </td>
            <td style="text-align:right;<?= (int) $row['total_undertime_minutes'] > 0 ? 'color:#b45309' : '' ?>">
                <?= $fmt((int) $row['total_undertime_minutes']) ?>
            </td>
            <td style="text-align:right;<?= (int) $row['total_overtime_minutes'] > 0 ? 'color:#1d4ed8' : '' ?>">
                <?= $fmt((int) $row['total_overtime_minutes']) ?>
            </td>
            <td style="text-align:center">
                <?php if ((int) $row['incomplete_days'] > 0): ?>
                <span class="badge badge-red"><?= (int) $row['incomplete_days'] ?></span>
                <?php else: ?>
                <span style="color:#9ca3af">—</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="/hr/attendance/<?= (int) $row['employee_id'] ?>"
                   class="btn btn-secondary btn-sm">Detail</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
