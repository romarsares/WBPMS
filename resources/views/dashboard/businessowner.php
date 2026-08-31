<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var list<array{value:mixed,label:string}> $stats */
/** @var string $base */
?>
<div class="page-head">
    <div>
        <h1>Owner Dashboard</h1>
        <p>Financial overview and final approval monitoring.</p>
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
        <h2>Owner Actions</h2>
        <div class="quick-actions">
            <a class="btn-primary" href="<?= $base ?>/requests">✓ Review Requests</a>
            <a class="btn-primary" href="<?= $base ?>/payroll">₱ Review Payroll</a>
            <a class="btn-secondary" href="<?= $base ?>/reports">▤ Financial Reports</a>
        </div>
    </div>
    <div class="panel">
        <h2>System Status</h2>
        <p>All payroll runs pending your approval are listed under <strong>Payroll Approval</strong>. Approved requests and returned payrolls are tracked in <strong>Reports</strong>.</p>
    </div>
</div>
