<?php


use Wbpms\Http\View\Formatter;

/** @var array{parsedTokens:int,matchedPunches:int,unmatchedPunches:int,duplicatePunches:int,incompleteDays:int,multiPunchDays:int,byBranch:array<int,array<string,int>>} $summary */
?>
<h1>Attendance import summary</h1>
<dl>
    <dt>Parsed punches</dt><dd><?= $summary['parsedTokens'] ?></dd>
    <dt>Matched</dt><dd><?= $summary['matchedPunches'] ?></dd>
    <dt>Unmatched</dt><dd><?= $summary['unmatchedPunches'] ?></dd>
    <dt>Duplicates skipped</dt><dd><?= $summary['duplicatePunches'] ?></dd>
    <dt>Incomplete days</dt><dd><?= $summary['incompleteDays'] ?></dd>
    <dt>Multi-punch days</dt><dd><?= $summary['multiPunchDays'] ?></dd>
</dl>
<table><thead><tr><th>Branch</th><th>Matched</th><th>Unmatched</th><th>Duplicates</th><th>Incomplete</th><th>Multi-punch</th></tr></thead><tbody>
<?php foreach ($summary['byBranch'] as $branchId => $counts): ?><tr><td><?= Formatter::escape($branchId) ?></td><td><?= $counts['matched'] ?></td><td><?= $counts['unmatched'] ?></td><td><?= $counts['duplicates'] ?></td><td><?= $counts['incomplete'] ?></td><td><?= $counts['multiPunch'] ?></td></tr><?php endforeach; ?>
</tbody></table>
