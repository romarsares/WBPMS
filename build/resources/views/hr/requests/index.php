<?php

use Wbpms\Http\View\Formatter;

/**
 * View: hr/requests/index
 *
 * Variables injected by RequestsController::index() + ViewRenderer:
 *   list<array<string,mixed>> $rows
 *   list<array{request_type_id:int,type_name:string}> $types
 *   array{status:string,type_id:int,search:string} $filters
 *   int $total, $pending, $approved, $rejected
 *   string $base   — injected by ViewRenderer (APP_BASE_URL, no trailing slash)
 *   string $csrf   — injected by ViewRenderer
 */

$rows     ??= [];
$types    ??= [];
$filters  ??= [];
$total    ??= 0;
$pending  ??= 0;
$approved ??= 0;
$rejected ??= 0;
$base     ??= '';
$csrf     ??= '';

/**
 * Build a short human-readable detail string for the type-specific column.
 * The list query in RequestService::list() does not JOIN the detail tables,
 * so we show the type name only; the full detail is on the show page.
 */
$typeBadgeColor = static function (string $type): string {
    return match ($type) {
        'Leave'       => '#6366f1',
        'Overtime'    => '#0ea5e9',
        'CashAdvance' => '#f59e0b',
        default       => '#6b7280',
    };
};

$statusColor = static function (string $status): string {
    return match ($status) {
        'Approved'  => '#10b981',
        'Rejected'  => '#ef4444',
        'Cancelled' => '#6b7280',
        default     => '#f59e0b', // Pending
    };
};
?>

<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem">
    <div>
        <h1 style="margin:0 0 .25rem">Request Management</h1>
        <p style="margin:0;color:#6b7280;font-size:.875rem">Review and action employee leave, overtime, and cash-advance requests.</p>
    </div>
    <a href="<?= $base ?>/hr/requests/new" class="btn btn-primary" style="white-space:nowrap">+ New Request</a>
</div>

<!-- Summary cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int) $total ?></span><span class="stat-label">Total</span></div>
    <div class="stat-card" style="border-left:4px solid #f59e0b"><span class="stat-value"><?= (int) $pending ?></span><span class="stat-label">Pending</span></div>
    <div class="stat-card" style="border-left:4px solid #10b981"><span class="stat-value"><?= (int) $approved ?></span><span class="stat-label">Approved</span></div>
    <div class="stat-card" style="border-left:4px solid #ef4444"><span class="stat-value"><?= (int) $rejected ?></span><span class="stat-label">Rejected</span></div>
</div>

<!-- Filters -->
<form method="GET" action="<?= $base ?>/hr/requests"
      style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.25rem">
    <div>
        <label style="font-size:.78rem;font-weight:500;color:#374151;display:block;margin-bottom:.2rem">Status</label>
        <select name="status" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
            <option value="">All statuses</option>
            <?php foreach (['Pending', 'Approved', 'Rejected', 'Cancelled'] as $s): ?>
            <option value="<?= Formatter::escape($s) ?>"
                <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
                <?= Formatter::escape($s) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="font-size:.78rem;font-weight:500;color:#374151;display:block;margin-bottom:.2rem">Type</label>
        <select name="type_id" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;min-width:160px">
            <option value="">All types</option>
            <?php foreach ($types as $t): ?>
            <option value="<?= (int) $t['request_type_id'] ?>"
                <?= (int) ($filters['type_id'] ?? 0) === (int) $t['request_type_id'] ? 'selected' : '' ?>>
                <?= Formatter::escape($t['type_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="font-size:.78rem;font-weight:500;color:#374151;display:block;margin-bottom:.2rem">Employee</label>
        <input type="text" name="search"
               value="<?= Formatter::escape($filters['search'] ?? '') ?>"
               placeholder="Name or number…"
               style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;min-width:200px">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if (!empty($filters['status']) || !empty($filters['type_id']) || !empty($filters['search'])): ?>
    <a href="<?= $base ?>/hr/requests" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
    <table class="data-table" style="width:100%;border-collapse:collapse">
        <thead>
            <tr>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">#</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Employee</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Type</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Reason</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Submitted</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Status</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr>
                <td colspan="7" style="padding:2rem;text-align:center;color:#6b7280;font-size:.875rem">
                    No requests found.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:.65rem 1rem;font-size:.85rem;color:#9ca3af"><?= (int) $r['request_id'] ?></td>
                <td style="padding:.65rem 1rem">
                    <strong style="font-size:.875rem"><?= Formatter::escape((string) $r['employee_name']) ?></strong><br>
                    <span style="font-size:.75rem;color:#6b7280"><?= Formatter::escape((string) $r['employee_number']) ?></span>
                </td>
                <td style="padding:.65rem 1rem">
                    <span style="background:<?= $typeBadgeColor($r['type_name']) ?>;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem;white-space:nowrap">
                        <?= Formatter::escape((string) $r['type_name']) ?>
                    </span>
                </td>
                <td style="padding:.65rem 1rem;max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.875rem">
                    <?= Formatter::escape(mb_strimwidth((string) ($r['reason'] ?? ''), 0, 80, '…')) ?>
                </td>
                <td style="padding:.65rem 1rem;font-size:.85rem;white-space:nowrap">
                    <?= Formatter::escape((string) $r['submitted_at']) ?>
                </td>
                <td style="padding:.65rem 1rem">
                    <span style="background:<?= $statusColor($r['status']) ?>;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem">
                        <?= Formatter::escape((string) $r['status']) ?>
                    </span>
                </td>
                <td style="padding:.65rem 1rem;white-space:nowrap">
                    <a href="<?= $base ?>/hr/requests/<?= (int) $r['request_id'] ?>"
                       class="btn btn-sm btn-secondary"
                       style="font-size:.78rem;padding:.3rem .6rem;margin-right:.3rem">
                        View
                    </a>
                    <?php if ($r['status'] === 'Pending'): ?>
                    <form method="POST"
                          action="<?= $base ?>/hr/requests/<?= (int) $r['request_id'] ?>/approve"
                          style="display:inline"
                          onsubmit="return confirm('Approve this request?')">
                        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                        <button type="submit"
                                class="btn btn-sm btn-primary"
                                style="font-size:.78rem;padding:.3rem .6rem">
                            Approve
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
