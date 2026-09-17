<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/** @var string $type @var string $title @var list<string> $headers @var list<list<string|int|float>> $rows @var array<string, int|string> $filters @var list<array{branch_id:string|int,branch_name:string}> $branches @var list<array{request_type_id:string|int,type_name:string}> $requestTypes */

$rows ??= [];
$filters ??= [];
$branches ??= [];
$requestTypes ??= [];
$hasDateFilter = in_array($type, ['payroll', 'attendance', 'requests', 'contributions'], true);
$hasBranchFilter = in_array($type, ['payroll', 'attendance', 'employees'], true);
$hasStatusFilter = in_array($type, ['attendance', 'requests', 'employees'], true);
$printQuery = http_build_query(array_filter(['type' => $type] + $filters, static fn(mixed $value): bool => $value !== '' && $value !== 0));
$statusOptions = match ($type) {
    'attendance' => ['Complete', 'Approved', 'Incomplete', 'ReviewRequired'],
    'requests' => ['Pending', 'Approved', 'Rejected', 'Cancelled'],
    'employees' => ['Active', 'Inactive', 'Separated', 'Archived'],
    default => [],
};
?>

<div class="page-header">
    <div>
        <h1><?= Formatter::escape($title) ?></h1>
        <p>Apply filters and review the results before printing.</p>
    </div>
    <a href="<?= $base ?>/hr/reports/export?<?= Formatter::escape($printQuery) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Print Report</a>
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
        <div><label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Status</label><select name="status"><option value="">All statuses</option><?php foreach ($statusOptions as $status): ?><option value="<?= $status ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <?php if ($type !== 'payroll'): ?>
        <div><label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Employee</label><input type="search" name="employee_search" value="<?= Formatter::escape((string) ($filters['employee_search'] ?? '')) ?>" placeholder="Name or employee no."></div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="<?= $base ?>/hr/reports/<?= Formatter::escape($type) ?>" class="btn btn-secondary">Clear</a>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <?php if ($rows === []): ?><p style="padding:1.5rem;color:#6b7280;margin:0">No records match the current filters.</p>
    <?php else: ?><table class="data-table"><thead><tr><?php foreach ($headers as $header): ?><th><?= Formatter::escape($header) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= Formatter::escape($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table><p style="padding:.75rem 1rem;margin:0;color:#6b7280;font-size:.85rem">Total records: <?= count($rows) ?></p><?php endif; ?>
</div>