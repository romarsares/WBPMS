<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR — Work schedules list with inline create form.
 *
 * @var list<array{
 *     id: int,
 *     employee_id: int,
 *     employee_name: string,
 *     name: string,
 *     time_in: string,
 *     time_out: string,
 *     work_days: string,
 *     grace_minutes: int,
 *     effective_from: string,
 *     status: string
 * }> $schedules
 * @var list<array{id:int,name:string}> $employees  For the employee dropdown
 * @var array<string,string>            $errors
 * @var string                          $csrf
 */

$schedules ??= [];
$employees ??= [];
$errors    ??= [];
$csrf      ??= '';

// Repopulate form fields on validation failure
$fp = static fn(string $key): string =>
    Formatter::escape((string) ($_POST[$key] ?? ''));
$err = static fn(string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . Formatter::escape($errors[$key]) . '</span>' : '';
?>

<div class="page-header">
    <h1>Work Schedules</h1>
</div>

<!-- Create form -->
<div class="card" style="max-width:700px;margin-bottom:1.75rem">
    <h2 style="margin-top:0;font-size:1rem">Add Work Schedule</h2>
    <form method="POST" action="/hr/schedules">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

        <div class="form-group">
            <label for="employee_id">Employee <span style="color:#dc2626">*</span></label>
            <select id="employee_id" name="employee_id"
                class="<?= isset($errors['employee_id']) ? 'is-invalid' : '' ?>" required>
                <option value="">— select employee —</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= (int) $emp['id'] ?>"
                    <?= ((string) $emp['id'] === $fp('employee_id')) ? 'selected' : '' ?>>
                    <?= Formatter::escape($emp['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?= $err('employee_id') ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="time_in">Time in (24h) <span style="color:#dc2626">*</span></label>
                <input type="time" id="time_in" name="time_in"
                    value="<?= $fp('time_in') ?>"
                    class="<?= isset($errors['time_in']) ? 'is-invalid' : '' ?>" required>
                <?= $err('time_in') ?>
            </div>
            <div class="form-group">
                <label for="time_out">Time out (24h) <span style="color:#dc2626">*</span></label>
                <input type="time" id="time_out" name="time_out"
                    value="<?= $fp('time_out') ?>"
                    class="<?= isset($errors['time_out']) ? 'is-invalid' : '' ?>" required>
                <?= $err('time_out') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="work_days">Work days</label>
                <input type="text" id="work_days" name="work_days"
                    value="<?= $fp('work_days') ?>"
                    placeholder="e.g. Mon–Fri">
                <small style="font-size:.78rem;color:#6b7280">Leave blank for Mon–Fri default.</small>
            </div>
            <div class="form-group">
                <label for="effective_from">Effective from</label>
                <input type="date" id="effective_from" name="effective_from"
                    value="<?= $fp('effective_from') ?>">
                <small style="font-size:.78rem;color:#6b7280">Defaults to today if blank.</small>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="flash flash-error" role="alert">
            Please correct the highlighted fields.
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Add Schedule</button>
    </form>
</div>

<!-- Existing schedules -->
<div class="card" style="padding:0;overflow:hidden">
    <?php if (empty($schedules)): ?>
    <p style="padding:1.25rem;color:#6b7280;font-size:.875rem;margin:0">
        No work schedules defined yet.
    </p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Work Days</th>
                <th>Effective From</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($schedules as $sched): ?>
        <?php
        $badge = match ($sched['status']) {
            'active'   => '<span class="badge badge-green">Active</span>',
            'archived' => '<span class="badge badge-gray">Archived</span>',
            default    => '<span class="badge badge-gray">' . Formatter::escape($sched['status']) . '</span>',
        };
        ?>
        <tr>
            <td><?= Formatter::escape($sched['employee_name'] ?? '—') ?></td>
            <td><?= Formatter::escape($sched['time_in']) ?></td>
            <td><?= Formatter::escape($sched['time_out']) ?></td>
            <td style="font-size:.8rem;color:#6b7280">
                <?php
                $wd = $sched['work_days'] ?? '';
                if ($wd !== '' && $wd[0] === '[') {
                    $arr = json_decode($wd, true);
                    echo Formatter::escape(is_array($arr) ? implode(', ', $arr) : $wd);
                } else {
                    echo Formatter::escape($wd);
                }
                ?>
            </td>
            <td><?= Formatter::date($sched['effective_from'] ?? '') ?></td>
            <td><?= $badge ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
