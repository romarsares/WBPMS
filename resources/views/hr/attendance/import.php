<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/** @var list<array{id:int,name:string}> $devices */
/** @var array<string,string> $errors */
?>
<h1>Import attendance workbook</h1>
<p>Choose the biometric device. Its site and covered branches are derived from configuration.</p>
<form method="post" action="/hr/attendance/import" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf ?? '') ?>">
    <label>Device <select required name="device_id"><?php foreach ($devices as $device): ?><option value="<?= $device['id'] ?>"><?= Formatter::escape($device['name']) ?></option><?php endforeach; ?></select></label>
    <label>Source year <input required type="number" name="source_year" min="2000" max="2100"></label>
    <label>Source month <input required type="number" name="source_month" min="1" max="12"></label>
    <label>Daily-log .xls file <input required type="file" name="attendance_file" accept=".xls,application/vnd.ms-excel"></label>
    <?php if (!empty($errors)): ?><p role="alert">The workbook was not imported. Review the listed validation errors.</p><?php endif; ?>
    <button type="submit">Upload and generate timesheets</button>
</form>
