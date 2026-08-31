<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var list<array{value:mixed,label:string}> $stats */
/** @var string $base */
?>
<div class="page-head">
    <div>
        <h1>HR Head Dashboard</h1>
        <p>Manage employee operations and payroll processing.</p>
    </div>
</div>

<div class="cards four">
    <?php foreach ($stats as $s): ?>
        <div class="stat">
            <strong><?= Formatter::escape((string) $s['value']) ?></strong>
            <span><?= Formatter::escape($s['label']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid-two">
    <div class="panel">
        <h2>Payroll Processing</h2>
        <p>Compute payroll and submit it to the Business Owner for final approval.</p>
        <a class="btn-primary" href="<?= $base ?>/payroll">Open Payroll Processing</a>
    </div>
    <div class="panel">
        <h2>Quick Actions</h2>
        <div class="quick-actions">
            <a class="btn-secondary" href="<?= $base ?>/attendance">◷ Attendance</a>
            <a class="btn-secondary" href="<?= $base ?>/requests">▱ Requests</a>
            <a class="btn-secondary" href="<?= $base ?>/employees">♟ Employees</a>
            <a class="btn-secondary" href="<?= $base ?>/reports">▤ Reports</a>
        </div>
    </div>
</div>
