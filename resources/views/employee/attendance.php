<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/** @var list<array{date:string,timeIn:?string,timeOut:?string,workedMinutes:int,lateMinutes:int,undertimeMinutes:int,overtimeMinutes:int,flags:list<string>}> $attendance */
?>
<h1>My attendance</h1>
<table><thead><tr><th>Date</th><th>Time in</th><th>Time out</th><th>Worked</th><th>Late</th><th>Undertime</th><th>Overtime</th><th>Review</th></tr></thead><tbody>
<?php foreach ($attendance as $day): ?><tr><td><?= Formatter::date($day['date']) ?></td><td><?= Formatter::time($day['timeIn']) ?></td><td><?= Formatter::time($day['timeOut']) ?></td><td><?= intdiv($day['workedMinutes'], 60) ?>h <?= $day['workedMinutes'] % 60 ?>m</td><td><?= $day['lateMinutes'] ?>m</td><td><?= $day['undertimeMinutes'] ?>m</td><td><?= $day['overtimeMinutes'] ?>m</td><td><?= Formatter::escape(implode(', ', $day['flags'])) ?></td></tr><?php endforeach; ?>
</tbody></table>
