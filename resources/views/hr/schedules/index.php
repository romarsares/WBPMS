<?php

use Wbpms\Http\View\Formatter;

/**
 * View: hr/schedules/index  (GET /hr/schedules)
 *
 * Variables:
 *   list<array> $schedules   — reusable schedule templates
 *   list<array> $holidays    — upcoming/recent holidays
 *   list<array> $assignments — all employee–schedule assignments
 *   int    $total, $active, $hTotal, $upcoming
 *   array  $errors           — validation errors for inline create form
 *   array  $input            — repopulate fields on validation failure
 */

$errors = $errors ?? [];
$input  = $input  ?? [];

$allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Helper: was this day checked on a failed form submit?
$dayChecked = static function (string $day) use ($input): bool {
    $days = $input['work_days'] ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    return is_array($days) && in_array($day, $days, true);
};

$v = static fn(string $key, string $fallback = ''): string =>
    Formatter::escape((string) ($input[$key] ?? $fallback));
?>

<div class="page-head">
    <div>
        <h1>Work Schedules</h1>
        <p>Create and manage reusable shift templates. Assign schedules to employees separately.</p>
    </div>
    <div style="display:flex;gap:.5rem">
        <button class="btn btn-primary" onclick="openModal('modalCreateSchedule')">+ New Schedule</button>
        <a class="btn btn-secondary" href="<?= $base ?>/hr/schedules/assign">Assign to Employee</a>
        <a class="btn btn-secondary" href="<?= $base ?>/hr/schedules/holidays">Manage Holidays</a>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int) $total ?></span><span class="stat-label">Total Schedules</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int) $active ?></span><span class="stat-label">Active</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int) $hTotal ?></span><span class="stat-label">Holidays on Record</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int) $upcoming ?></span><span class="stat-label">Upcoming Holidays</span></div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start">

    <!-- Left column: schedules table + assignments -->
    <div>
        <!-- Schedule Templates -->
        <div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem">
            <div style="padding:14px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
                <strong>Schedule Templates</strong>
                <div class="local-search-wrap">⌕<input placeholder="Search schedules..." oninput="filterRows(this,'schedTable')"></div>
            </div>
            <table class="data-table" id="schedTable">
                <thead>
                    <tr>
                        <th>Schedule</th>
                        <th>Working Days</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Break</th>
                        <th>Effective From</th>
                        <th>Effective To</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($schedules)): ?>
                    <tr><td colspan="9" class="muted" style="text-align:center;padding:24px">No schedules defined yet. Click <strong>+ New Schedule</strong> to create one.</td></tr>
                <?php else: foreach ($schedules as $s):
                    // Legacy display key retained while schedule templates are rendered by this view.
                    $s['employee_name'] = $s['schedule_name'] ?? '';
                    $days = $s['working_days'] ?? '[]';
                    if (is_string($days)) {
                        $decoded = json_decode($days, true);
                        $days = is_array($decoded)
                            ? implode(', ', array_map(fn($d) => substr($d, 0, 3), $decoded))
                            : $days;
                    }
                    $badge = strtolower($s['status']) === 'active' ? 'ok' : 'off';
                    ?>
                    <tr>
                        <td><strong><?= Formatter::escape($s['employee_name'] ?? '—') ?></strong><br>
                            <small class="muted"><?= Formatter::escape($s['employee_number'] ?? '') ?></small></td>
                        <td style="font-size:13px"><?= Formatter::escape($days) ?></td>
                        <td><?= Formatter::escape($s['time_in'] ?? '—') ?></td>
                        <td><?= Formatter::escape($s['time_out'] ?? '—') ?></td>
                        <td><?= (int) ($s['break_minutes'] ?? 0) ?>m</td>
                        <td><?= Formatter::escape($s['effective_from'] ?? '—') ?></td>
                        <td><?= $s['effective_to'] ? Formatter::escape($s['effective_to']) : '<span class="muted">current</span>' ?></td>
                        <td><span class="badge <?= $badge ?>"><?= Formatter::escape(ucfirst($s['status'])) ?></span></td>
                        <td>
                            <a href="<?= $base ?>/hr/schedules/<?= (int) $s['id'] ?>/edit"
                               class="btn btn-secondary" style="padding:4px 10px;font-size:12px">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Employee Assignments -->
        <div class="card" style="padding:0;overflow:hidden">
            <div style="padding:14px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
                <strong>Employee Schedule Assignments</strong>
                <a href="<?= $base ?>/hr/schedules/assign" class="btn btn-secondary" style="padding:4px 12px;font-size:13px">+ Assign</a>
            </div>
            <table class="data-table" id="assignTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Time</th>
                        <th>Effective From</th>
                        <th>Effective To</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($assignments)): ?>
                    <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">No assignments yet.</td></tr>
                <?php else: foreach ($assignments as $a):
                    $badge = strtolower($a['status']) === 'active' ? 'ok' : 'off';
                    ?>
                    <tr>
                        <td>
                            <strong><?= Formatter::escape($a['employee_name'] ?? '—') ?></strong><br>
                            <small class="muted"><?= Formatter::escape($a['employee_number'] ?? '') ?></small>
                        </td>
                        <td><?= Formatter::escape($a['time_in'] ?? '—') ?> – <?= Formatter::escape($a['time_out'] ?? '—') ?></td>
                        <td><?= Formatter::escape($a['effective_from'] ?? '—') ?></td>
                        <td><?= $a['effective_to'] ? Formatter::escape($a['effective_to']) : '<span class="muted">current</span>' ?></td>
                        <td><span class="badge <?= $badge ?>"><?= Formatter::escape(ucfirst($a['status'])) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right column: holidays -->
    <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:14px 20px;border-bottom:1px solid var(--line)">
            <strong>Upcoming &amp; Recent Holidays</strong>
        </div>
        <table class="data-table">
            <thead>
                <tr><th>Holiday</th><th>Date</th><th>Type</th></tr>
            </thead>
            <tbody>
            <?php if (empty($holidays)): ?>
                <tr><td colspan="3" class="muted" style="text-align:center;padding:24px">No holidays on record.</td></tr>
            <?php else: foreach ($holidays as $h):
                $badge = $h['holiday_type'] === 'Regular' ? 'ok' : 'wait'; ?>
                <tr>
                    <td><?= Formatter::escape($h['holiday_name']) ?></td>
                    <td style="white-space:nowrap"><?= Formatter::escape($h['holiday_date']) ?></td>
                    <td><span class="badge <?= $badge ?>"><?= Formatter::escape($h['holiday_type']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- =====================================================================
     MODAL: Create New Schedule
     ===================================================================== -->
<div id="modalCreateSchedule" class="modal <?= $errors !== [] ? 'show' : '' ?>">
    <div class="modal-card" style="max-width:620px">
        <div class="modal-header">
            <h3 style="margin:0">New Work Schedule</h3>
            <button type="button" onclick="closeModal('modalCreateSchedule')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="<?= $base ?>/hr/schedules">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <!-- Schedule Name -->
            <div class="form-group">
                <label for="schedule_name">Schedule Name <span style="color:#dc2626">*</span></label>
                <input type="text" id="schedule_name" name="schedule_name"
                       value="<?= $v('schedule_name') ?>"
                       placeholder="e.g. Regular Shift, Morning Shift"
                       class="<?= isset($errors['schedule_name']) ? 'is-invalid' : '' ?>" required>
                <?php if (isset($errors['schedule_name'])): ?>
                    <small class="field-error"><?= Formatter::escape($errors['schedule_name']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Working Days -->
            <div class="form-group">
                <label>Working Days <span style="color:#dc2626">*</span></label>
                <div style="display:flex;flex-wrap:wrap;gap:.5rem .75rem;margin-top:.35rem">
                    <?php foreach ($allDays as $day): ?>
                    <label style="display:flex;align-items:center;gap:.3rem;font-weight:400;cursor:pointer">
                        <input type="checkbox" name="work_days[]" value="<?= $day ?>"
                               <?= $dayChecked($day) ? 'checked' : '' ?>>
                        <?= substr($day, 0, 3) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['work_days'])): ?>
                    <small class="field-error"><?= Formatter::escape($errors['work_days']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Time In / Time Out -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="time_in">Time In <span style="color:#dc2626">*</span></label>
                    <input type="time" id="time_in" name="time_in"
                           value="<?= $v('time_in') ?>"
                           class="<?= isset($errors['time_in']) ? 'is-invalid' : '' ?>" required>
                    <?php if (isset($errors['time_in'])): ?>
                        <small class="field-error"><?= Formatter::escape($errors['time_in']) ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="time_out">Time Out <span style="color:#dc2626">*</span></label>
                    <input type="time" id="time_out" name="time_out"
                           value="<?= $v('time_out') ?>"
                           class="<?= isset($errors['time_out']) ? 'is-invalid' : '' ?>" required>
                    <?php if (isset($errors['time_out'])): ?>
                        <small class="field-error"><?= Formatter::escape($errors['time_out']) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Break Time -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="break_minutes">Break Duration (min)</label>
                    <input type="number" id="break_minutes" name="break_minutes"
                           value="<?= $v('break_minutes', '60') ?>"
                           min="0" max="480" step="5">
                    <?php if (isset($errors['break_minutes'])): ?>
                        <small class="field-error"><?= Formatter::escape($errors['break_minutes']) ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="break_start">Break Start</label>
                    <input type="time" id="break_start" name="break_start"
                           value="<?= $v('break_start') ?>"
                           placeholder="12:00">
                </div>
                <div class="form-group">
                    <label for="break_end">Break End</label>
                    <input type="time" id="break_end" name="break_end"
                           value="<?= $v('break_end') ?>"
                           placeholder="13:00">
                </div>
            </div>

            <!-- Grace Period / Overtime -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="grace_minutes">Grace Period (min)</label>
                    <input type="number" id="grace_minutes" name="grace_minutes"
                           value="<?= $v('grace_minutes', '0') ?>"
                           min="0" max="120" step="1"
                           placeholder="e.g. 15">
                    <?php if (isset($errors['grace_minutes'])): ?>
                        <small class="field-error"><?= Formatter::escape($errors['grace_minutes']) ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="overtime_allowed">Overtime Allowed</label>
                    <select id="overtime_allowed" name="overtime_allowed">
                        <option value="1" <?= $v('overtime_allowed', '1') === '1' ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= $v('overtime_allowed', '1') === '0' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
            </div>

            <!-- Effective From / Status -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="effective_from">Effective From</label>
                    <input type="date" id="effective_from" name="effective_from"
                           value="<?= $v('effective_from', date('Y-m-d')) ?>">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Active"   <?= $v('status', 'Active') === 'Active'   ? 'selected' : '' ?>>Active</option>
                        <option value="Archived" <?= $v('status', 'Active') === 'Archived' ? 'selected' : '' ?>>Archived / Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Notes -->
            <div class="form-group">
                <label for="notes">Notes / Description <span class="muted">(optional)</span></label>
                <textarea id="notes" name="notes" rows="2"
                          placeholder="Additional details about this schedule…"
                          style="width:100%;resize:vertical"><?= $v('notes') ?></textarea>
            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:.5rem">
                <button type="button" onclick="closeModal('modalCreateSchedule')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Schedule</button>
            </div>
        </form>
    </div>
</div>
