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
 *     hr_note: string|null
 * }> $requests
 * @var string $filterStatus
 * @var string $csrf
 */

$requests     ??= [];
$filterStatus ??= '';
$csrf         ??= '';

$statusBadge = static function (string $status): string {
    return match ($status) {
        'Pending'   => '<span class="badge badge-yellow">Pending</span>',
        'Approved'  => '<span class="badge badge-green">Approved</span>',
        'Rejected'  => '<span class="badge badge-red">Rejected</span>',
        'Cancelled' => '<span class="badge badge-gray">Cancelled</span>',
        default     => '<span class="badge badge-gray">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
    };
};
?>

<div class="page-header">
    <h1>My Requests</h1>
    <a href="<?= $base ?>/employee/requests/new" class="btn btn-primary">New Request</a>
</div>

<!-- Status filter -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/employee/requests"
          style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Status</label>
            <select name="status" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All statuses</option>
                <option value="Pending"   <?= $filterStatus === 'Pending'   ? 'selected' : '' ?>>Pending</option>
                <option value="Approved"  <?= $filterStatus === 'Approved'  ? 'selected' : '' ?>>Approved</option>
                <option value="Rejected"  <?= $filterStatus === 'Rejected'  ? 'selected' : '' ?>>Rejected</option>
                <option value="Cancelled" <?= $filterStatus === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
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
                <?= Formatter::escape($req['summary']) ?>
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
