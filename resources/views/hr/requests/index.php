<?php
/**
 * View: hr/requests/index
 * Variables: $rows, $types, $filters, $total, $pending, $approved, $rejected
 */
?>
<div class="page-head">
    <div>
        <h1>Request Management</h1>
        <p>Review and action employee leave, overtime, and cash-advance requests.</p>
    </div>
</div>

<!-- Summary cards -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= $total ?></span><span class="stat-label">Total</span></div>
    <div class="stat-card" style="border-left:4px solid #f59e0b"><span class="stat-value"><?= $pending ?></span><span class="stat-label">Pending</span></div>
    <div class="stat-card" style="border-left:4px solid #10b981"><span class="stat-value"><?= $approved ?></span><span class="stat-label">Approved</span></div>
    <div class="stat-card" style="border-left:4px solid #ef4444"><span class="stat-value"><?= $rejected ?></span><span class="stat-label">Rejected</span></div>
</div>

<!-- Filters -->
<form method="get" action="" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem">
    <select name="status" class="form-control" style="width:160px">
        <option value="">All Statuses</option>
        <?php foreach (['Pending','Approved','Rejected','Cancelled'] as $s): ?>
        <option value="<?= htmlspecialchars($s) ?>"<?= ($filters['status'] ?? '') === $s ? ' selected' : '' ?>><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="type_id" class="form-control" style="width:180px">
        <option value="">All Types</option>
        <?php foreach ($types as $t): ?>
        <option value="<?= (int)$t['request_type_id'] ?>"<?= (int)($filters['type_id'] ?? 0) === (int)$t['request_type_id'] ? ' selected' : '' ?>><?= htmlspecialchars($t['type_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="search" class="form-control" placeholder="Employee name or number…" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="min-width:220px">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="/hr/requests" class="btn btn-secondary">Clear</a>
</form>

<!-- Table -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Type</th>
                <th>Reason</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:2rem">No requests found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int)$r['request_id'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($r['employee_name']) ?></strong><br>
                    <small style="color:#6b7280"><?= htmlspecialchars($r['employee_number']) ?></small>
                </td>
                <td><?= htmlspecialchars($r['type_name']) ?></td>
                <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars((string)$r['reason']) ?></td>
                <td><?= htmlspecialchars((string)$r['submitted_at']) ?></td>
                <td>
                    <?php
                    $badgeColors = ['Pending'=>'#f59e0b','Approved'=>'#10b981','Rejected'=>'#ef4444','Cancelled'=>'#6b7280'];
                    $c = $badgeColors[$r['status']] ?? '#6b7280';
                    ?>
                    <span style="background:<?= $c ?>;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem"><?= htmlspecialchars($r['status']) ?></span>
                </td>
                <td style="white-space:nowrap">
                    <a href="/hr/requests/<?= (int)$r['request_id'] ?>" class="btn btn-sm btn-secondary">View</a>
                    <?php if ($r['status'] === 'Pending'): ?>
                    <form method="post" action="/hr/requests/<?= (int)$r['request_id'] ?>/approve" style="display:inline">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Approve this request?')">Approve</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
