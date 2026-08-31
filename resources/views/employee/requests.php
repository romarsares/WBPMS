<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var array[] $rows @var int $total @var int $pending @var int $approved @var int $rejected @var string $base @var string $displayName */
?>
<div class="page-head">
    <div><h1>My Requests</h1><p>Your leave, overtime, and cash advance submissions.</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Total Submitted</span></div>
    <div class="stat"><strong style="color:var(--wait)"><?= $pending ?></strong><span>Pending</span></div>
    <div class="stat"><strong style="color:var(--ok)"><?= $approved ?></strong><span>Approved</span></div>
    <div class="stat"><strong style="color:var(--bad)"><?= $rejected ?></strong><span>Rejected</span></div>
</div>
<div class="filterbar">
    <div class="local-search-wrap">⌕<input placeholder="Search requests..." oninput="filterRows(this,'myReqTable')"></div>
</div>
<div class="panel table-wrap">
    <table id="myReqTable">
        <thead>
            <tr><th>TYPE</th><th>SUBMITTED</th><th>STATUS</th><th>REMARKS</th><th>HR NOTE</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;padding:28px">You have not submitted any requests yet.</td></tr>
        <?php else: foreach ($rows as $r):
            $badge = match($r['status']) { 'Approved' => 'ok', 'Rejected' => 'bad', 'Cancelled' => 'off', default => 'wait' }; ?>
            <tr>
                <td style="font-weight:700"><?= Formatter::escape($r['type_name']) ?></td>
                <td><?= Formatter::date($r['submitted_at']) ?></td>
                <td><span class="badge <?= $badge ?>"><?= Formatter::escape($r['status']) ?></span></td>
                <td class="muted"><?= Formatter::escape(mb_strimwidth((string)($r['remarks'] ?? ''), 0, 60, '…')) ?></td>
                <td class="muted"><?= Formatter::escape((string)($r['hr_note'] ?? '—')) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
