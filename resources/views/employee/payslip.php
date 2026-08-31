<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/** @var array{period:string,gross:string,deductions:string,net:string,items:list<array{label:string,amount:string}>} $payslip */
?>
<h1>Payslip: <?= Formatter::escape($payslip['period']) ?></h1>
<table><tbody><?php foreach ($payslip['items'] as $item): ?><tr><th><?= Formatter::escape($item['label']) ?></th><td><?= Formatter::escape($item['amount']) ?></td></tr><?php endforeach; ?></tbody></table>
<p>Gross: <?= Formatter::escape($payslip['gross']) ?> · Deductions: <?= Formatter::escape($payslip['deductions']) ?> · Net pay: <?= Formatter::escape($payslip['net']) ?></p>
<a href="<?= Formatter::escape($downloadUrl ?? '#') ?>">Download payslip</a>
