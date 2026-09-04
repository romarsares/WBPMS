<?php
/**
 * HR — Holiday Calendar management.
 *
 * @var list<array{holiday_id: int, holiday_date: string, description: string, holiday_type: string, pay_multiplier: string, status: string}> $holidays
 * @var array<string, string> $errors
 * @var array<string, string> $input   repopulate on error
 * @var string                $csrf
 */
$input = $input ?? [];
?>

<div class="page-header">
    <h1>Holiday Calendar</h1>
    <a href="/hr/schedules" class="btn btn-secondary">← Schedules</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $msg): ?>
            <p><?= htmlspecialchars($msg, ENT_QUOTES) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add Holiday Form -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><strong>Add Holiday</strong></div>
    <div class="card-body">
        <form method="POST" action="/hr/schedules/holidays" class="form-inline-grid">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

            <div class="form-group <?= isset($errors['holiday_date']) ? 'has-error' : '' ?>">
                <label for="holiday_date">Date</label>
                <input type="date" name="holiday_date" id="holiday_date" class="form-control"
                       value="<?= htmlspecialchars($input['holiday_date'] ?? '', ENT_QUOTES) ?>" required>
                <?php if (isset($errors['holiday_date'])): ?>
                    <span class="error-text"><?= htmlspecialchars($errors['holiday_date'], ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['description']) ? 'has-error' : '' ?>">
                <label for="description">Name / Description</label>
                <input type="text" name="description" id="description" class="form-control"
                       value="<?= htmlspecialchars($input['description'] ?? '', ENT_QUOTES) ?>"
                       placeholder="e.g. New Year's Day" maxlength="100" required>
                <?php if (isset($errors['description'])): ?>
                    <span class="error-text"><?= htmlspecialchars($errors['description'], ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['holiday_type']) ? 'has-error' : '' ?>">
                <label for="holiday_type">Type</label>
                <select name="holiday_type" id="holiday_type" class="form-control" required>
                    <option value="">— Select —</option>
                    <option value="Regular"  <?= ($input['holiday_type'] ?? '') === 'Regular'  ? 'selected' : '' ?>>Regular (200%)</option>
                    <option value="Special"  <?= ($input['holiday_type'] ?? '') === 'Special'  ? 'selected' : '' ?>>Special (130%)</option>
                </select>
                <?php if (isset($errors['holiday_type'])): ?>
                    <span class="error-text"><?= htmlspecialchars($errors['holiday_type'], ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Holiday</button>
            </div>
        </form>
    </div>
</div>

<!-- Holiday List -->
<div class="card">
    <div class="card-header">
        <strong>All Holidays</strong>
        <span class="badge"><?= count($holidays) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if ($holidays === []): ?>
            <p class="empty-state">No holidays defined yet.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Multiplier</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($holidays as $h): ?>
                        <tr class="<?= $h['status'] === 'Inactive' ? 'row-muted' : '' ?>">
                            <td><?= htmlspecialchars($h['holiday_date'], ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars($h['description'], ENT_QUOTES) ?></td>
                            <td>
                                <span class="badge badge-<?= $h['holiday_type'] === 'Regular' ? 'primary' : 'warning' ?>">
                                    <?= htmlspecialchars($h['holiday_type'], ENT_QUOTES) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string) $h['pay_multiplier'], ENT_QUOTES) ?>×</td>
                            <td>
                                <span class="badge badge-<?= $h['status'] === 'Active' ? 'success' : 'secondary' ?>">
                                    <?= htmlspecialchars($h['status'], ENT_QUOTES) ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="/hr/schedules/holidays/<?= (int) $h['holiday_id'] ?>/edit"
                                   class="btn btn-sm btn-secondary">Edit</a>
                                <?php if ($h['status'] === 'Active'): ?>
                                    <form method="POST"
                                          action="/hr/schedules/holidays/<?= (int) $h['holiday_id'] ?>/delete"
                                          style="display:inline;"
                                          onsubmit="return confirm('Deactivate this holiday?');">
                                        <input type="hidden" name="_csrf"
                                               value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Deactivate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
