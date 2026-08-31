<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/** @var list<array{id:int,name:string}> $branches */
/** @var list<array{id:int,name:string}> $schedules */
/** @var list<array{id:int,name:string}> $devices */
/** @var array<string,string> $errors */
?>
<h1>Add employee for attendance</h1>
<form method="post" action="/hr/employees">
    <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf ?? '') ?>">
    <label>Employee number <input required name="employee_number"></label>
    <label>First name <input required name="first_name"></label>
    <label>Last name <input required name="last_name"></label>
    <label>Effective from <input required type="date" name="effective_from"></label>
    <label>Branch <select required name="branch_id"><?php foreach ($branches as $branch): ?><option value="<?= $branch['id'] ?>"><?= Formatter::escape($branch['name']) ?></option><?php endforeach; ?></select></label>
    <label>Schedule <select required name="schedule_id"><?php foreach ($schedules as $schedule): ?><option value="<?= $schedule['id'] ?>"><?= Formatter::escape($schedule['name']) ?></option><?php endforeach; ?></select></label>
    <label>Biometric device <select required name="device_id"><?php foreach ($devices as $device): ?><option value="<?= $device['id'] ?>"><?= Formatter::escape($device['name']) ?></option><?php endforeach; ?></select></label>
    <label>Enroll ID <input required name="enrollment_code" inputmode="numeric"></label>
    <?php if (!empty($errors)): ?><p role="alert">Please correct the highlighted fields.</p><?php endif; ?>
    <button type="submit">Create employee</button>
</form>
