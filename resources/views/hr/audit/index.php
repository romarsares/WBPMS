<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * View: hr/audit/index  (GET /hr/audit)
 *
 * @var list<array<string,mixed>>   $rows
 * @var int                         $total
 * @var int                         $page
 * @var int                         $totalPages
 * @var array<string, mixed>        $filters
 * @var list<string>                $eventTypes
 * @var list<string>                $tablesAffected
 * @var list<array<string,mixed>>   $users
 * @var int                         $pageSize
 */

$rows           ??= [];
$total          ??= 0;
$page           ??= 1;
$totalPages     ??= 1;
$filters        ??= [];
$eventTypes     ??= [];
$tablesAffected ??= [];
$users          ??= [];
$pageSize       ??= 50;

// Build query string for pagination links, preserving active filters
$filterQuery = http_build_query(array_filter([
    'user_id'        => $filters['user_id']        ?? '',
    'event_type'     => $filters['event_type']     ?? '',
    'table_affected' => $filters['table_affected'] ?? '',
    'date_from'      => $filters['date_from']      ?? '',
    'date_to'        => $filters['date_to']        ?? '',
    'ip_address'     => $filters['ip_address']     ?? '',
    'search'         => $filters['search']         ?? '',
], static fn(mixed $v): bool => $v !== '' && $v !== null));

$paginationBase = $base . '/hr/audit?' . ($filterQuery !== '' ? $filterQuery . '&' : '');

// Row offset for page-aware numbering (page 1 = rows 1–50, page 2 = rows 51–100, etc.)
$rowOffset = ($page - 1) * ($pageSize ?? 50);
?>

<div class="page-header">
    <div>
        <h1>Audit Trail</h1>
        <p>Immutable record of all user actions across the system.</p>
    </div>
    <a href="<?= $base ?>/hr/reports" class="btn btn-secondary">← Back to Reports</a>
</div>

<!-- Filters -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/hr/audit"
          style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">

        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">User</label>
            <select name="user_id">
                <option value="">All users</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['user_id'] ?>"
                        <?= (int) ($filters['user_id'] ?? 0) === (int) $u['user_id'] ? 'selected' : '' ?>>
                        <?= Formatter::escape($u['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Event Type</label>
            <select name="event_type">
                <option value="">All events</option>
                <?php foreach ($eventTypes as $et): ?>
                    <option value="<?= Formatter::escape($et) ?>"
                        <?= ($filters['event_type'] ?? '') === $et ? 'selected' : '' ?>>
                        <?= Formatter::escape($et) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Table</label>
            <select name="table_affected">
                <option value="">All tables</option>
                <?php foreach ($tablesAffected as $tbl): ?>
                    <option value="<?= Formatter::escape($tbl) ?>"
                        <?= ($filters['table_affected'] ?? '') === $tbl ? 'selected' : '' ?>>
                        <?= Formatter::escape($tbl) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">From</label>
            <input type="date" name="date_from"
                   value="<?= Formatter::escape((string) ($filters['date_from'] ?? '')) ?>">
        </div>

        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">To</label>
            <input type="date" name="date_to"
                   value="<?= Formatter::escape((string) ($filters['date_to'] ?? '')) ?>">
        </div>

        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Search</label>
            <input type="search" name="search" placeholder="Action, description, user…"
                   value="<?= Formatter::escape((string) ($filters['search'] ?? '')) ?>"
                   style="width:200px">
        </div>

        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="<?= $base ?>/hr/audit" class="btn btn-secondary">Clear</a>
    </form>
</div>

<!-- Result count -->
<p style="font-size:.85rem;color:#6b7280;margin-bottom:.5rem">
    <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found
    <?php if ($totalPages > 1): ?>
        — page <?= (int) $page ?> of <?= (int) $totalPages ?>
    <?php endif; ?>
</p>

<!-- Data table -->
<div class="card" style="padding:0;overflow-x:auto">
    <?php if ($rows === []): ?>
        <p style="padding:1.5rem;color:#6b7280;margin:0">No audit records match the current filters.</p>
    <?php else: ?>
        <table class="data-table" style="width:100%;table-layout:fixed">
            <colgroup>
                <col style="width:40px">  <!-- # Row number -->
                <col style="width:140px"><!-- Date/Time -->
                <col style="width:120px"><!-- User -->
                <col style="width:100px"><!-- Role -->
                <col style="width:150px"><!-- Event Type -->
                <col style="width:180px"><!-- Action Performed -->
                <col style="width:110px"><!-- Table Affected -->
                <col style="width:75px"> <!-- Record ID -->
                <col>                    <!-- Description (fills remaining space) -->
            </colgroup>
            <thead>
                <tr>
                    <th style="text-align:center">#</th>
                    <th>Date / Time</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Event Type</th>
                    <th>Action Performed</th>
                    <th>Table Affected</th>
                    <th style="text-align:center">Record ID</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowNum = $rowOffset + 1; foreach ($rows as $row): ?>
                    <tr>
                        <td style="text-align:center;font-size:.75rem;color:#9ca3af;user-select:none"><?= $rowNum++ ?></td>
                        <td style="font-size:.8rem;white-space:nowrap">
                            <?= Formatter::escape((string) ($row['action_at'] ?? '')) ?>
                        </td>
                        <td style="font-size:.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                            title="<?= Formatter::escape((string) ($row['username'] ?? $row['attempted_identifier'] ?? '')) ?>">
                            <?php if ($row['username'] !== null): ?>
                                <?= Formatter::escape((string) $row['username']) ?>
                            <?php elseif (!empty($row['attempted_identifier'])): ?>
                                <span style="color:#6b7280;font-style:italic">
                                    <?= Formatter::escape((string) $row['attempted_identifier']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#9ca3af">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?= Formatter::escape((string) ($row['role_name'] ?? '—')) ?>
                        </td>
                        <td>
                            <?php
                            $et         = (string) ($row['event_type'] ?? '');
                            $badgeStyle = match (true) {
                                str_contains($et, 'failure') || str_contains($et, 'error')  => 'background:#fee2e2;color:#991b1b',
                                str_contains($et, 'login')                                   => 'background:#dbeafe;color:#1e40af',
                                str_contains($et, 'delete') || str_contains($et, 'archive') => 'background:#fef3c7;color:#92400e',
                                str_contains($et, 'approve')                                 => 'background:#d1fae5;color:#065f46',
                                default                                                       => 'background:#f3f4f6;color:#374151',
                            };
                            ?>
                            <span style="font-size:.72rem;font-weight:600;padding:.15rem .45rem;border-radius:4px;<?= $badgeStyle ?>;display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                  title="<?= Formatter::escape($et) ?>">
                                <?= Formatter::escape($et) ?>
                            </span>
                        </td>
                        <td style="font-size:.82rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                            title="<?= Formatter::escape((string) ($row['action_performed'] ?? '')) ?>">
                            <?= Formatter::escape((string) ($row['action_performed'] ?? '')) ?>
                        </td>
                        <td style="font-size:.8rem;color:#4b5563;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?= Formatter::escape((string) ($row['table_affected'] ?? '—')) ?>
                        </td>
                        <td style="text-align:center;font-size:.8rem;color:#6b7280">
                            <?= $row['record_id'] !== null ? (int) $row['record_id'] : '—' ?>
                        </td>
                        <td style="font-size:.8rem;color:#374151;word-break:break-word;white-space:normal">
                            <?= Formatter::escape((string) ($row['description'] ?? '')) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="padding:.6rem 1rem;margin:0;color:#6b7280;font-size:.82rem;border-top:1px solid #f0f0f0">
            Showing <?= count($rows) ?> of <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?>
        </p>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <nav style="display:flex;gap:.5rem;justify-content:center;margin-top:1.25rem;flex-wrap:wrap"
         aria-label="Audit trail pagination">
        <?php if ($page > 1): ?>
            <a href="<?= $paginationBase ?>page=<?= $page - 1 ?>"
               class="btn btn-secondary" style="padding:.35rem .75rem">&larr; Prev</a>
        <?php endif; ?>

        <?php
        // Show a window of up to 7 page links around the current page
        $start = max(1, $page - 3);
        $end   = min($totalPages, $page + 3);
        if ($start > 1):
        ?>
            <a href="<?= $paginationBase ?>page=1" class="btn btn-secondary" style="padding:.35rem .75rem">1</a>
            <?php if ($start > 2): ?>
                <span style="line-height:2;color:#6b7280">…</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $start; $p <= $end; $p++): ?>
            <?php if ($p === $page): ?>
                <span class="btn btn-primary" style="padding:.35rem .75rem;cursor:default"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= $paginationBase ?>page=<?= $p ?>"
                   class="btn btn-secondary" style="padding:.35rem .75rem"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
                <span style="line-height:2;color:#6b7280">…</span>
            <?php endif; ?>
            <a href="<?= $paginationBase ?>page=<?= $totalPages ?>"
               class="btn btn-secondary" style="padding:.35rem .75rem"><?= $totalPages ?></a>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
            <a href="<?= $paginationBase ?>page=<?= $page + 1 ?>"
               class="btn btn-secondary" style="padding:.35rem .75rem">Next &rarr;</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
