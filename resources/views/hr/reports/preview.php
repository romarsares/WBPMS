<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/** @var string $type @var string $title @var list<string> $headers @var list<list<string|int|float>> $rows @var array<string, int|string> $filters @var list<array{branch_id:string|int,branch_name:string}> $branches @var list<array{request_type_id:string|int,type_name:string}> $requestTypes */

$rows ??= [];
$filters ??= [];
$branches ??= [];
$requestTypes ??= [];
$page ??= 1;
$totalPages ??= 1;
$total ??= 0;
$pageSize ??= 50;
$hasDateFilter = in_array($type, ['payroll', 'attendance', 'requests', 'contributions'], true);
$hasBranchFilter = in_array($type, ['payroll', 'attendance', 'employees'], true);
$hasStatusFilter = in_array($type, ['attendance', 'requests', 'employees'], true);
$printQuery = http_build_query(array_filter(['type' => $type] + $filters, static fn(mixed $value): bool => $value !== '' && $value !== 0));
$statusOptions = match ($type) {
    'attendance' => ['Complete', 'Approved', 'Incomplete', 'ReviewRequired'],
    'requests' => ['Pending', 'HRApproved', 'Approved', 'Cancelled'],
    'employees' => ['Active', 'Inactive', 'Separated', 'Archived'],
    default => [],
};
?>

<div class="page-header">
    <div>
        <h1><?= Formatter::escape($title) ?></h1>
        <p>Apply filters and review the results before exporting.</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <a href="<?= $base ?>/hr/reports" class="btn btn-secondary">← Back</a>
        <a href="<?= $base ?>/hr/reports/export?<?= Formatter::escape($printQuery) ?>"
           class="btn btn-secondary" target="_blank" rel="noopener">🖨 Print</a>
        <a href="<?= $base ?>/hr/reports/export?<?= Formatter::escape($printQuery) ?>&amp;format=pdf"
           class="btn btn-secondary" target="_blank" rel="noopener">⬇ PDF</a>
        <a href="<?= $base ?>/hr/reports/export?<?= Formatter::escape($printQuery) ?>&amp;format=csv"
           class="btn btn-primary">⬇ CSV</a>
    </div>
</div>

<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/hr/reports/<?= Formatter::escape($type) ?>" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <?php if ($hasDateFilter): ?>
        <div><label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">From</label><input type="date" name="date_from" value="<?= Formatter::escape((string) ($filters['date_from'] ?? '')) ?>"></div>
        <div><label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">To</label><input type="date" name="date_to" value="<?= Formatter::escape((string) ($filters['date_to'] ?? '')) ?>"></div>
        <?php endif; ?>
        <?php if ($hasBranchFilter): ?>
        <div><label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Branch</label><select name="branch_id"><option value="">All branches</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['branch_id'] ?>" <?= (int) $branch['branch_id'] === (int) ($filters['branch_id'] ?? 0) ? 'selected' : '' ?>><?= Formatter::escape($branch['branch_name']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <?php if ($type === 'requests'): ?>
        <div><label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Request type</label><select name="type_id"><option value="">All types</option><?php foreach ($requestTypes as $requestType): ?><option value="<?= (int) $requestType['request_type_id'] ?>" <?= (int) $requestType['request_type_id'] === (int) ($filters['type_id'] ?? 0) ? 'selected' : '' ?>><?= Formatter::escape($requestType['type_name']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <?php if ($type === 'contributions'): ?>
        <div><label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Program</label><select name="program"><option value="">All programs</option><?php foreach (['SSS', 'PhilHealth', 'PagIBIG'] as $program): ?><option value="<?= $program ?>" <?= ($filters['program'] ?? '') === $program ? 'selected' : '' ?>><?= $program ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <?php if ($hasStatusFilter): ?>
        <div><label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Status</label><select name="status"><option value="">All</option><?php foreach ($statusOptions as $status): ?><option value="<?= $status ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <?php if ($type !== 'payroll'): ?>
        <div><label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Employee</label><input type="search" name="employee_search" value="<?= Formatter::escape((string) ($filters['employee_search'] ?? '')) ?>" placeholder="Name or employee no."></div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="<?= $base ?>/hr/reports/<?= Formatter::escape($type) ?>" class="btn btn-secondary">Clear</a>
        <div style="display:flex;align-items:flex-end;margin-left:auto">
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Per page</label>
            <select name="page_size" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="10" <?= $pageSize === 10 ? 'selected' : '' ?>>10</option>
                <option value="20" <?= $pageSize === 20 ? 'selected' : '' ?>>20</option>
                <option value="30" <?= $pageSize === 30 ? 'selected' : '' ?>>30</option>
                <option value="50" <?= $pageSize === 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $pageSize === 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <?php if ($rows === []): ?>
        <p style="padding:1.5rem;color:#6b7280;margin:0">No records match the current filters.</p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><?php foreach ($headers as $header): ?><th><?= Formatter::escape($header) ?></th><?php endforeach; ?></tr></thead>
            <tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= Formatter::escape($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
        </table>
        <p style="padding:.75rem 1rem;margin:0;color:#6b7280;font-size:.85rem">Total records: <?= count($rows) ?></p>
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
        records
    </div>
    <nav aria-label="Report preview pagination">
        <ul style="display:flex;gap:.25rem;list-style:none;margin:0;padding:0">
            <!-- Previous -->
            <?php if ($page > 1): ?>
            <li>
                <a href="<?= $base ?>/hr/reports/<?= Formatter::escape($type) ?>?page=<?= $page - 1 ?>&<?= http_build_query($filters) ?>&page_size=<?= $pageSize ?>"
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
                <a href="<?= $base ?>/hr/reports/<?= Formatter::escape($type) ?>?page=1&<?= http_build_query($filters) ?>&page_size=<?= $pageSize ?>"
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
                <a href="<?= $base ?>/hr/reports/<?= Formatter::escape($type) ?>?page=<?= $p ?>&<?= http_build_query($filters) ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $p ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <li>
                <a href="<?= $base ?>/hr/reports/<?= Formatter::escape($type) ?>?page=<?= $totalPages ?>&<?= http_build_query($filters) ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $totalPages ?></a>
            </li>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
            <li>
                <a href="<?= $base ?>/hr/reports/<?= Formatter::escape($type) ?>?page=<?= $page + 1 ?>&<?= http_build_query($filters) ?>&page_size=<?= $pageSize ?>"
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