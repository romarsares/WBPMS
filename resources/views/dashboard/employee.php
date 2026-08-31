<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var list<array{value:mixed,label:string}> $stats */
/** @var string $displayName */
/** @var string $base */
?>
<div class="page-head">
    <div>
        <h1>Employee Dashboard</h1>
        <p>Welcome back, <?= Formatter::escape($displayName) ?>.</p>
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
        <h2>Quick Actions</h2>
        <div class="quick-actions">
            <a class="btn-secondary" href="<?= $base ?>/my-requests">▱ Submit Request</a>
            <a class="btn-secondary" href="<?= $base ?>/my-attendance">◷ My Attendance</a>
            <a class="btn-secondary" href="<?= $base ?>/my-payslips">▤ My Payslips</a>
        </div>
    </div>
    <div class="panel">
        <h2>Leave Entitlement</h2>
        <p>You are entitled to <strong>4 paid sick leaves</strong> per year. Submit a leave request under <strong>Leave / OT / Cash Advance</strong>.</p>
    </div>
</div>
