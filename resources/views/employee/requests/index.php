<?php


use Wbpms\Http\View\Formatter;

/**
 * Employee portal — My requests list.
 *
 * @var list<array{
 *     id: int,
 *     type: string,
 *     submitted_at: string,
 *     status: string,
 *     summary: string,
 *     hr_note: string|null,
 *     leave_start_date?: string,
 *     leave_end_date?: string,
 *     leave_days?: float
 * }> $requests
 * @var string $filterStatus
 * @var string $csrf
 * @var int $page
 * @var int $totalPages
 * @var int $total
 * @var int $pageSize
 */

$requests               ??= [];
$filterStatus           ??= '';
$csrf                   ??= '';
$sickLeaveBalance       ??= 0.0;
$sickLeaveLimit         ??= 4;
$pendingLeaveDays       ??= 0.0;
$leaveBalanceAfterPending ??= (float) $sickLeaveBalance;
$cashAdvanceOutstanding ??= 0.0;
$cashAdvanceMax         ??= null;
$page                   ??= 1;
$totalPages             ??= 1;
$total                  ??= 0;
$pageSize               ??= 50;

$statusBadge = static function (string $status): string {
    return match ($status) {
        'Pending'    => '<span class="badge badge-yellow">Pending</span>',
        'HRApproved' => '<span class="badge badge-purple" style="background:#6366f1;color:#fff">Awaiting Owner</span>',
        'Approved'   => '<span class="badge badge-green">Approved</span>',
        'Returned'   => '<span class="badge badge-orange" style="background:#f97316;color:#fff">Returned</span>',
        'Cancelled'  => '<span class="badge badge-gray">Cancelled</span>',
        default      => '<span class="badge badge-gray">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
    };
};
?>

<div class="page-header">
    <div>
        <h1>My Requests</h1>
        <a href="<?= $base ?>/employee/dashboard" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
    <a href="<?= $base ?>/employee/requests/new" class="btn btn-primary">New Request</a>
</div>

<!-- Leave balance + cash advance summary -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">

    <!-- Sick Leave card -->
    <div class="card" style="padding:1rem 1.25rem">
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.6rem">
            <span style="font-size:1.5rem;line-height:1">🌿</span>
            <span style="font-size:.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Sick Leave</span>
        </div>
        <div style="display:flex;align-items:baseline;gap:.4rem">
            <span style="font-size:1.75rem;font-weight:700;color:<?= (float)$sickLeaveBalance > 0 ? '#059669' : '#dc2626' ?>">
                <?= number_format((float)$sickLeaveBalance, 1) ?>
            </span>
            <span style="font-size:.9rem;color:#6b7280">/ <?= (int)$sickLeaveLimit ?> days</span>
        </div>
        <div style="margin-top:.5rem">
            <?php
            $pct = $sickLeaveLimit > 0 ? min(100, ((float)$sickLeaveBalance / (int)$sickLeaveLimit) * 100) : 0;
            $barColor = $pct > 50 ? '#10b981' : ($pct > 25 ? '#f59e0b' : '#ef4444');
            ?>
            <div style="height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?= round($pct) ?>%;background:<?= $barColor ?>;border-radius:3px;transition:width .3s"></div>
            </div>
            <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem">
                <?= number_format((float)$sickLeaveBalance, 1) ?> day<?= (float)$sickLeaveBalance !== 1.0 ? 's' : '' ?> remaining &nbsp;·&nbsp; Limit: <?= (int)$sickLeaveLimit ?> days/year
            </div>
            <?php if ((float)$pendingLeaveDays > 0): ?>
            <div style="font-size:.75rem;margin-top:.25rem;color:#d97706">
                <?= number_format((float)$pendingLeaveDays, 1) ?> day<?= (float)$pendingLeaveDays !== 1.0 ? 's' : '' ?> pending approval
                &nbsp;·&nbsp;
                <strong style="color:<?= (float)$leaveBalanceAfterPending < 0 ? '#dc2626' : '#374151' ?>">
                    <?= number_format((float)$leaveBalanceAfterPending, 1) ?> left after
                </strong>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cash Advance card -->
    <div class="card" style="padding:1rem 1.25rem">
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.6rem">
            <span style="font-size:1.5rem;line-height:1">💵</span>
            <span style="font-size:.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Cash Advance</span>
        </div>
        <?php if ($cashAdvanceMax !== null): ?>
        <div style="display:flex;align-items:baseline;gap:.4rem">
            <span style="font-size:1.75rem;font-weight:700;color:<?= (float)$cashAdvanceOutstanding > 0 ? '#d97706' : '#059669' ?>">
                ₱<?= number_format((float)$cashAdvanceOutstanding, 2) ?>
            </span>
            <span style="font-size:.9rem;color:#6b7280">outstanding</span>
        </div>
        <div style="margin-top:.5rem">
            <?php
            $caPct = $cashAdvanceMax > 0 ? min(100, ((float)$cashAdvanceOutstanding / $cashAdvanceMax) * 100) : 0;
            $caBar = $caPct < 50 ? '#10b981' : ($caPct < 80 ? '#f59e0b' : '#ef4444');
            ?>
            <div style="height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?= round($caPct) ?>%;background:<?= $caBar ?>;border-radius:3px;transition:width .3s"></div>
            </div>
            <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem">
                Unpaid: ₱<?= number_format((float)$cashAdvanceOutstanding, 2) ?> &nbsp;·&nbsp; Max: ₱<?= number_format((float)$cashAdvanceMax, 2) ?> per month &nbsp;·&nbsp; ₱500.00 per week only
            </div>
        </div>
        <?php else: ?>
        <div style="font-size:1.1rem;font-weight:600;color:#6b7280">No salary record on file</div>
        <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem">Contact HR to set up your salary record.</div>
        <?php endif; ?>
    </div>

</div>

<!-- Status filter + Per-page selector -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/employee/requests"
          style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Status</label>
            <select name="status" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All</option>
                <option value="Pending"   <?= $filterStatus === 'Pending'   ? 'selected' : '' ?>>Pending</option>
                <option value="Approved"  <?= $filterStatus === 'Approved'  ? 'selected' : '' ?>>Approved</option>
                <option value="Cancelled" <?= $filterStatus === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;margin-left:auto">
            <label for="page_size" style="font-size:.8rem;font-weight:500;color:#374151;white-space:nowrap">Per page</label>
            <select id="page_size" name="page_size" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="10" <?= $pageSize === 10 ? 'selected' : '' ?>>10</option>
                <option value="20" <?= $pageSize === 20 ? 'selected' : '' ?>>20</option>
                <option value="30" <?= $pageSize === 30 ? 'selected' : '' ?>>30</option>
                <option value="50" <?= $pageSize === 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $pageSize === 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filterStatus !== ''): ?>
        <a href="<?= $base ?>/employee/requests" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Requests table -->
<div class="card" style="padding:0;overflow:hidden">
    <?php if (empty($requests)): ?>
    <p style="padding:1.5rem;color:#6b7280;font-size:.875rem;margin:0">
        No requests found.
        <a href="<?= $base ?>/employee/requests/new">Submit your first request →</a>
    </p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Details</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>HR Note</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $req): ?>
        <tr>
            <td style="white-space:nowrap;font-weight:500"><?= Formatter::escape($req['type']) ?></td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?php if ($req['type'] === 'Leave' && isset($req['leave_start_date'], $req['leave_end_date'])): ?>
                    <?php
                    $leaveStart = Formatter::escape($req['leave_start_date']);
                    $leaveEnd   = Formatter::escape($req['leave_end_date']);
                    $leaveDays  = isset($req['leave_days']) ? (float) $req['leave_days'] : 0.0;
                    ?>
                    <span style="white-space:nowrap">
                        <?= $leaveStart ?> – <?= $leaveEnd ?>
                    </span>
                    <?php if ($leaveDays > 0): ?>
                    <span style="display:inline-block;margin-left:.35rem;font-size:.78rem;color:#6b7280;white-space:nowrap">
                        (<?= number_format($leaveDays, 0) ?> day<?= $leaveDays !== 1.0 ? 's' : '' ?>)
                    </span>
                    <?php endif; ?>
                <?php else: ?>
                    <?= Formatter::escape($req['summary']) ?>
                <?php endif; ?>
            </td>
            <td><?= Formatter::date($req['submitted_at']) ?></td>
            <td><?= $statusBadge($req['status']) ?></td>
            <td style="font-size:.82rem;color:#6b7280">
                <?= $req['hr_note'] !== null ? Formatter::escape($req['hr_note']) : '—' ?>
            </td>
            <td>
                <?php if ($req['status'] === 'Pending'): ?>
                <form method="POST"
                      action="<?= $base ?>/employee/requests/<?= (int) $req['id'] ?>/cancel"
                      style="display:inline"
                      onsubmit="return confirm('Cancel this request?')">
                    <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                </form>
                <?php else: ?>
                <span style="color:#9ca3af;font-size:.8rem">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="card" style="padding:1rem 1.25rem;margin-top:1rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem">
    <div style="font-size:.875rem;color:#374151">
        Showing
        <strong><?= (int) (($page - 1) * $pageSize + 1) ?></strong>
        to
        <strong><?= (int) min($page * $pageSize, $total) ?></strong>
        of
        <strong><?= (int) $total ?></strong>
        requests
    </div>
    <nav aria-label="Request pagination">
        <ul style="display:flex;gap:.25rem;list-style:none;margin:0;padding:0">
            <!-- Previous -->
            <?php if ($page > 1): ?>
            <li>
                <a href="<?= $base ?>/employee/requests?page=<?= $page - 1 ?>&status=<?= urlencode($filterStatus) ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm" aria-label="Previous page">« Prev</a>
            </li>
            <?php else: ?>
            <li>
                <span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:not-allowed" aria-disabled="true">« Prev</span>
            </li>
            <?php endif; ?>

            <!-- Page numbers -->
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            if ($start > 1): ?>
            <li>
                <a href="<?= $base ?>/employee/requests?page=1&status=<?= urlencode($filterStatus) ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm">1</a>
            </li>
            <?php if ($start > 2): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
            <li>
                <?php if ($p === $page): ?>
                <span class="btn btn-primary btn-sm" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                <a href="<?= $base ?>/employee/requests?page=<?= $p ?>&status=<?= urlencode($filterStatus) ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $p ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <li>
                <a href="<?= $base ?>/employee/requests?page=<?= $totalPages ?>&status=<?= urlencode($filterStatus) ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $totalPages ?></a>
            </li>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
            <li>
                <a href="<?= $base ?>/employee/requests?page=<?= $page + 1 ?>&status=<?= urlencode($filterStatus) ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm" aria-label="Next page">Next »</a>
            </li>
            <?php else: ?>
            <li>
                <span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:not-allowed" aria-disabled="true">Next »</span>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>
