<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * View: hr/requests/cash-advance-tracker
 * Accessible at:
 *   GET /hr/requests/cash-advance-tracker   (HRHead)
 *   GET /owner/requests/cash-advance-tracker (BusinessOwner)
 *
 * @var list<array{cash_advance_id:int,employee_id:int,employee_number:string,employee_name:string,branch_name:string,original_amount:float,remaining_balance:float,ca_status:string,approved_at:string,request_reason:string,request_id:int}> $rows
 * @var float $totalOutstanding
 * @var bool  $showAll
 */

$rows             ??= [];
$totalOutstanding ??= 0.0;
$showAll          ??= false;

$role        = $_SESSION['role'] ?? 'HRHead';
$backLink    = $role === 'BusinessOwner' ? $base . '/owner/requests' : $base . '/hr/requests';
$trackerBase = $role === 'BusinessOwner'
    ? $base . '/owner/requests/cash-advance-tracker'
    : $base . '/hr/requests/cash-advance-tracker';
?>

<div class="page-header">
    <div>
        <h1>Cash Advance Balances</h1>
        <p>Employee cash advance obligations and repayment progress.</p>
    </div>
    <a href="<?= $backLink ?>" class="btn btn-secondary">← Back to Requests</a>
</div>

<!-- Summary cards -->
<?php
$open   = count(array_filter($rows, static fn(array $r): bool => $r['ca_status'] === 'Active' && (float) $r['remaining_balance'] > 0));
$repaid = count(array_filter($rows, static fn(array $r): bool => (float) $r['remaining_balance'] <= 0 || $r['ca_status'] !== 'Active'));
?>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card" style="border-left:4px solid #d97706">
        <span class="stat-value" style="color:#d97706">₱<?= number_format($totalOutstanding, 2) ?></span>
        <span class="stat-label">Total Outstanding</span>
    </div>
    <div class="stat-card" style="border-left:4px solid #ef4444">
        <span class="stat-value"><?= $open ?></span>
        <span class="stat-label">Open Balances</span>
    </div>
    <div class="stat-card" style="border-left:4px solid #10b981">
        <span class="stat-value"><?= $repaid ?></span>
        <span class="stat-label">Fully Repaid</span>
    </div>
</div>

<!-- Toggle filter -->
<div style="margin-bottom:1.25rem;display:flex;gap:.75rem;align-items:center">
    <?php if (!$showAll): ?>
    <a href="<?= $trackerBase ?>?show_all=1" class="btn btn-secondary" style="font-size:.85rem">Show All (incl. repaid)</a>
    <span style="font-size:.82rem;color:#6b7280">Showing open balances only.</span>
    <?php else: ?>
    <a href="<?= $trackerBase ?>" class="btn btn-secondary" style="font-size:.85rem">Show Open Only</a>
    <span style="font-size:.82rem;color:#6b7280">Showing all records.</span>
    <?php endif; ?>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
    <?php if ($rows === []): ?>
    <p style="padding:2rem;text-align:center;color:#6b7280;margin:0">
        <?= $showAll ? 'No cash advance records found.' : 'No open cash advance balances.' ?>
    </p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Branch</th>
                <th style="text-align:right">Original</th>
                <th style="text-align:right">Remaining</th>
                <th style="text-align:center">Repayment Progress</th>
                <th>Status</th>
                <th>Approved</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $orig = (float) $r['original_amount'];
            $rem  = (float) $r['remaining_balance'];
            $paid = $orig - $rem;
            $pct  = $orig > 0 ? min(100, max(0, ($paid / $orig) * 100)) : 100;
            $bar  = $pct >= 100 ? '#10b981' : ($pct >= 50 ? '#6366f1' : '#f59e0b');
            $isOpen = $r['ca_status'] === 'Active' && $rem > 0;
        ?>
        <tr style="<?= !$isOpen ? 'opacity:.55' : '' ?>">
            <td>
                <div style="font-weight:500"><?= Formatter::escape($r['employee_name']) ?></div>
                <div style="font-size:.75rem;color:#6b7280"><?= Formatter::escape($r['employee_number']) ?></div>
            </td>
            <td style="font-size:.85rem"><?= Formatter::escape($r['branch_name'] ?? '—') ?></td>
            <td style="text-align:right">₱<?= number_format($orig, 2) ?></td>
            <td style="text-align:right;font-weight:700;color:<?= $rem > 0 ? '#d97706' : '#059669' ?>">
                ₱<?= number_format($rem, 2) ?>
            </td>
            <td style="min-width:130px;padding:.5rem 1rem">
                <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;margin-bottom:3px">
                    <div style="height:100%;width:<?= round($pct) ?>%;background:<?= $bar ?>;border-radius:4px"></div>
                </div>
                <div style="font-size:.72rem;color:#6b7280;text-align:center"><?= round($pct) ?>% repaid</div>
            </td>
            <td>
                <?php if (!$isOpen): ?>
                <span style="background:#d1fae5;color:#065f46;padding:1px 8px;border-radius:9999px;font-size:.75rem">Repaid</span>
                <?php else: ?>
                <span style="background:#fef3c7;color:#92400e;padding:1px 8px;border-radius:9999px;font-size:.75rem">Open</span>
                <?php endif; ?>
            </td>
            <td style="font-size:.8rem;color:#6b7280;white-space:nowrap">
                <?= Formatter::escape($r['approved_at'] ?? '—') ?>
            </td>
            <td style="font-size:.82rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= Formatter::escape(mb_strimwidth((string) ($r['request_reason'] ?? ''), 0, 60, '…')) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($totalOutstanding > 0): ?>
        <tfoot>
            <tr style="background:#f8fafc;font-weight:600">
                <td colspan="3" style="padding:.6rem 1rem;text-align:right">Total Outstanding</td>
                <td style="text-align:right;padding:.6rem .75rem;color:#d97706">
                    ₱<?= number_format($totalOutstanding, 2) ?>
                </td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <?php endif; ?>
</div>
