<?php


use Wbpms\Http\View\Formatter;

/**
 * HR — Employee list.
 *
 * @var list<array{
 *     id: int,
 *     employee_number: string,
 *     last_name: string,
 *     first_name: string,
 *     branch_name: string,
 *     schedule_name: string,
 *     status: string,
 *     effective_from: string
 * }> $employees
 * @var string $search         Current search query
 * @var string $filterBranch   Current branch filter (id as string, or '')
 * @var string $filterStatus   Current status filter ('active'|'inactive'|'')
 * @var list<array{id:int,name:string}> $branches   For filter dropdown
 */

$employees    ??= [];
$search       ??= '';
$filterBranch ??= '';
$filterStatus ??= '';
$branches     ??= [];
?>

<div class="page-header">
    <h1>Employees</h1>
    <a href="<?= $base ?>/hr/employees/new" class="btn btn-primary">Add Employee</a>
</div>

<!-- Filters -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/hr/employees" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Search</label>
            <input
                type="text"
                name="search"
                value="<?= Formatter::escape($search) ?>"
                placeholder="Name or employee no."
                style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;width:200px"
            >
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Branch</label>
            <select name="branch" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All branches</option>
                <?php foreach ($branches as $branch): ?>
                <option value="<?= (int) $branch['id'] ?>"
                    <?= ((string) $branch['id'] === $filterBranch) ? 'selected' : '' ?>>
                    <?= Formatter::escape($branch['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Status</label>
            <select name="status" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All statuses</option>
                <option value="active"   <?= $filterStatus === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($search !== '' || $filterBranch !== '' || $filterStatus !== ''): ?>
        <a href="<?= $base ?>/hr/employees" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
    <?php if (empty($employees)): ?>
    <p style="padding:1.5rem;color:#6b7280;font-size:.875rem;margin:0">
        No employees match the current filters.
    </p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Employee No.</th>
                <th>Name</th>
                <th>Branch</th>
                <th>Schedule</th>
                <th>Effective From</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $emp): ?>
        <?php
        $statusBadge = match ($emp['status']) {
            'active'   => '<span class="badge badge-green">Active</span>',
            'inactive' => '<span class="badge badge-yellow">Inactive</span>',
            'archived' => '<span class="badge badge-gray">Archived</span>',
            default    => '<span class="badge badge-gray">' . Formatter::escape($emp['status']) . '</span>',
        };
        ?>
        <tr>
            <td><code style="font-size:.8rem"><?= Formatter::escape($emp['employee_number']) ?></code></td>
            <td>
                <a href="<?= $base ?>/hr/employees/<?= (int) $emp['id'] ?>">
                    <?= Formatter::escape($emp['last_name']) ?>, <?= Formatter::escape($emp['first_name']) ?>
                </a>
            </td>
            <td><?= Formatter::escape($emp['branch_name']) ?></td>
            <td><?= Formatter::escape($emp['schedule_name']) ?></td>
            <td><?= Formatter::date($emp['effective_from']) ?></td>
            <td><?= $statusBadge ?></td>
            <td style="white-space:nowrap">
                <a href="<?= $base ?>/hr/employees/<?= (int) $emp['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                <a href="<?= $base ?>/hr/employees/<?= (int) $emp['id'] ?>/edit" class="btn btn-secondary btn-sm">Edit</a>
                <?php if ($emp['status'] === 'archived'): ?>
                <a href="<?= $base ?>/hr/employees/<?= (int) $emp['id'] ?>/rehire" class="btn btn-primary btn-sm">Rehire</a>
                <?php else: ?>
                <a href="<?= $base ?>/hr/employees/<?= (int) $emp['id'] ?>/archive" class="btn btn-secondary btn-sm">Archive</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
