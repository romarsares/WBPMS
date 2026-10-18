<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * View: hr/requests/leave-credits  (GET /hr/requests/leave-credits)
 *
 * @var list<array{employee_id:int,employee_number:string,employee_name:string,entitled_days:float,balance:float,used:float}> $rows
 * @var int $year
 * @var int $currentYear
 */

$rows        ??= [];
$year        ??= (int) date('Y');
$currentYear ??= (int) date('Y');
?>

<div class="page-header">
    <div>
        <h1>Leave Credit Management</h1>
        <p>View and adjust employee sick leave balances.</p>
    </div>
    <a href="<?= $base ?>/hr/requests" class="btn btn-secondary">← Back to Requests</a>
</div>

<!-- Year filter -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/hr/requests/leave-credits"
          style="display:flex;gap:.75rem;align-items:flex-end">
        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Year</label>
            <select name="year" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <?php for ($y = $currentYear; $y >= $currentYear - 3; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>
</div>

<!-- Summary cards -->
<?php
$totalGranted = count($rows) * 4;
$totalUsed    = array_sum(array_column($rows, 'used'));
$totalBalance = array_sum(array_column($rows, 'balance'));
$depleted     = count(array_filter($rows, static fn(array $r): bool => (float) $r['balance'] <= 0));
?>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= count($rows) ?></span><span class="stat-label">Active Employees</span></div>
    <div class="stat-card" style="border-left:4px solid #10b981"><span class="stat-value"><?= number_format($totalBalance, 1) ?></span><span class="stat-label">Total Days Remaining</span></div>
    <div class="stat-card" style="border-left:4px solid #f59e0b"><span class="stat-value"><?= number_format($totalUsed, 1) ?></span><span class="stat-label">Total Days Used</span></div>
    <div class="stat-card" style="border-left:4px solid #ef4444"><span class="stat-value"><?= $depleted ?></span><span class="stat-label">Depleted / No Balance</span></div>
</div>

<!-- Employee table -->
<div class="card" style="padding:0;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid #e5e7eb">
        <strong>Sick Leave Balances — <?= $year ?></strong>
        <span style="font-size:.8rem;color:#6b7280;margin-left:.5rem">(4 days/year per ADR-0001)</span>
    </div>
    <?php if ($rows === []): ?>
    <p style="padding:2rem;text-align:center;color:#6b7280">No active employees found.</p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee #</th>
                <th>Name</th>
                <th style="text-align:center">Entitled</th>
                <th style="text-align:center">Used</th>
                <th style="text-align:center">Remaining</th>
                <th style="text-align:center">Balance Bar</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $bal = (float) $r['balance'];
            $ent = max(1, (float) $r['entitled_days']);
            $pct = min(100, max(0, ($bal / $ent) * 100));
            $barColor = $pct > 50 ? '#10b981' : ($pct > 25 ? '#f59e0b' : '#ef4444');
        ?>
        <tr>
            <td style="font-size:.82rem;color:#6b7280"><?= Formatter::escape($r['employee_number']) ?></td>
            <td style="font-weight:500"><?= Formatter::escape($r['employee_name']) ?></td>
            <td style="text-align:center"><?= number_format((float) $r['entitled_days'], 1) ?></td>
            <td style="text-align:center;color:#f59e0b"><?= number_format((float) $r['used'], 1) ?></td>
            <td style="text-align:center;font-weight:700;color:<?= $bal > 0 ? '#059669' : '#dc2626' ?>">
                <?= number_format($bal, 1) ?>
            </td>
            <td style="min-width:120px;padding:.5rem 1rem">
                <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden">
                    <div style="height:100%;width:<?= round($pct) ?>%;background:<?= $barColor ?>;border-radius:4px"></div>
                </div>
            </td>
            <td>
                <a href="<?= $base ?>/hr/requests/leave-credits/<?= (int) $r['employee_id'] ?>?year=<?= $year ?>"
                   class="btn btn-secondary" style="font-size:.78rem;padding:.3rem .6rem;white-space:nowrap">
                    View / Adjust
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
