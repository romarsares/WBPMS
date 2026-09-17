<?php
use Wbpms\Http\View\Formatter;
/** @var array[] $rows @var int $total @var int $active @var int $regular @var int $contractual @var string $base */
?>
<div class="page-head">
    <div><h1>Employee Management</h1><p>Employee records, branch assignments, and salary overview.</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Total Employees</span></div>
    <div class="stat"><strong><?= $active ?></strong><span>Active</span></div>
    <div class="stat"><strong><?= $regular ?></strong><span>Regular</span></div>
    <div class="stat"><strong><?= $contractual ?></strong><span>Contractual</span></div>
</div>
<div class="filterbar">
    <div class="local-search-wrap">⌕<input placeholder="Search employees..." oninput="filterRows(this,'empTable')"></div>
</div>
<div class="panel table-wrap">
    <table id="empTable">
        <thead>
            <tr>
                <th>EMP NO.</th><th>NAME</th><th>POSITION</th><th>TYPE</th>
                <th>BRANCH</th><th>DAILY RATE</th><th>STATUS</th><th>HIRED</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="muted" style="text-align:center;padding:28px">No employees found.</td></tr>
        <?php else: foreach ($rows as $r):
            $badge = match($r['status']) { 'Active' => 'ok', 'Inactive' => 'off', default => 'bad' }; ?>
            <tr>
                <td><?= Formatter::escape($r['employee_number']) ?></td>
                <td><?= Formatter::escape($r['last_name'] . ', ' . $r['first_name']) ?></td>
                <td><?= Formatter::escape($r['position'] ?? '—') ?></td>
                <td><?= Formatter::escape($r['employee_type'] ?? '—') ?></td>
                <td><?= Formatter::escape($r['branch_name'] ?? '—') ?></td>
                <td><?= $r['daily_rate'] !== null ? '₱' . number_format((float)$r['daily_rate'], 2) : '—' ?></td>
                <td><span class="badge <?= $badge ?>"><?= Formatter::escape($r['status']) ?></span></td>
                <td><?= Formatter::date($r['hire_date'] ?? '') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
