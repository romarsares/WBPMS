<?php
/**
 * View: hr/salary/index
 * Variables: $rows, $filters, $branches, $total, $active, $avgRate, $maxRate
 */
?>
<div class="page-head">
    <div>
        <h1>Salary Management</h1>
        <p>Manage employee daily rates and salary history.</p>
    </div>
    <a href="<?= $base ?>/hr/salary/create" class="btn btn-primary">+ Add Salary Record</a>
</div>

<!-- Summary -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= $total ?></span><span class="stat-label">Records</span></div>
    <div class="stat-card"><span class="stat-value"><?= $active ?></span><span class="stat-label">Active</span></div>
    <div class="stat-card"><span class="stat-value">₱<?= number_format($avgRate, 2) ?></span><span class="stat-label">Avg Daily Rate</span></div>
    <div class="stat-card"><span class="stat-value">₱<?= number_format($maxRate, 2) ?></span><span class="stat-label">Highest Rate</span></div>
</div>

<!-- Filters -->
<form method="get" action="" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem">
    <input type="text" name="search" class="form-control" placeholder="Employee name or number…"
           value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="min-width:220px">
    <select name="branch_id" class="form-control" style="width:180px">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
        <option value="<?= (int)$b['branch_id'] ?>"<?= (int)($filters['branch_id'] ?? 0) === (int)$b['branch_id'] ? ' selected' : '' ?>><?= htmlspecialchars($b['branch_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" class="form-control" style="width:160px">
        <option value="Active"<?= ($filters['status'] ?? '') === 'Active' ? ' selected' : '' ?>>Active</option>
        <option value="Superseded"<?= ($filters['status'] ?? '') === 'Superseded' ? ' selected' : '' ?>>Superseded</option>
        <option value="Archived"<?= ($filters['status'] ?? '') === 'Archived' ? ' selected' : '' ?>>Archived</option>
        <option value=""<?= ($filters['status'] ?? 'Active') === '' ? ' selected' : '' ?>>All</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="<?= $base ?>/hr/salary" class="btn btn-secondary">Clear</a>
</form>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Branch</th>
                <th>Daily Rate</th>
                <th>Effective From</th>
                <th>Effective To</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:2rem">No salary records found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($r['employee_name']) ?></strong><br>
                    <small style="color:#6b7280"><?= htmlspecialchars($r['employee_number']) ?></small>
                </td>
                <td><?= htmlspecialchars((string)($r['branch_name'] ?? '—')) ?></td>
                <td>₱<?= number_format((float)$r['daily_rate'], 2) ?></td>
                <td><?= htmlspecialchars((string)$r['effective_from']) ?></td>
                <td><?= $r['effective_to'] ? htmlspecialchars($r['effective_to']) : '—' ?></td>
                <td>
                    <?php
                    $c = ['Active'=>'#10b981','Superseded'=>'#f59e0b','Archived'=>'#6b7280'][$r['status']] ?? '#6b7280';
                    ?>
                    <span style="background:<?= $c ?>;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem"><?= htmlspecialchars($r['status']) ?></span>
                </td>
                <td style="white-space:nowrap">
                    <?php if ($r['status'] === 'Active'): ?>
                    <a href="<?= $base ?>/hr/salary/<?= (int)$r['salary_id'] ?>/edit" class="btn btn-sm btn-secondary">Update Rate</a>
                    <form method="post" action="<?= $base ?>/hr/salary/<?= (int)$r['salary_id'] ?>/archive" style="display:inline">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Archive this salary record?')">Archive</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
