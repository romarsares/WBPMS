<?php
/**
 * HR — Edit Holiday.
 *
 * @var array{holiday_id: int, holiday_date: string, description: string, holiday_type: string, status: string} $holiday
 * @var array<string, string> $errors
 * @var string                $csrf
 */
$id = (int) $holiday['holiday_id'];
?>

<div class="page-header">
    <h1>Edit Holiday</h1>
    <a href="/hr/schedules/holidays" class="btn btn-secondary">← Holiday Calendar</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $msg): ?>
            <p><?= htmlspecialchars($msg, ENT_QUOTES) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/hr/schedules/holidays/<?= $id ?>">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <input type="hidden" name="_method" value="PUT">

            <div class="form-group <?= isset($errors['holiday_date']) ? 'has-error' : '' ?>">
                <label for="holiday_date">Date <span class="required">*</span></label>
                <input type="date" name="holiday_date" id="holiday_date" class="form-control"
                       value="<?= htmlspecialchars($holiday['holiday_date'], ENT_QUOTES) ?>" required>
                <?php if (isset($errors['holiday_date'])): ?>
                    <span class="error-text"><?= htmlspecialchars($errors['holiday_date'], ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['description']) ? 'has-error' : '' ?>">
                <label for="description">Name / Description <span class="required">*</span></label>
                <input type="text" name="description" id="description" class="form-control"
                       value="<?= htmlspecialchars($holiday['description'], ENT_QUOTES) ?>"
                       maxlength="100" required>
                <?php if (isset($errors['description'])): ?>
                    <span class="error-text"><?= htmlspecialchars($errors['description'], ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['holiday_type']) ? 'has-error' : '' ?>">
                <label for="holiday_type">Type <span class="required">*</span></label>
                <select name="holiday_type" id="holiday_type" class="form-control" required>
                    <option value="Regular" <?= $holiday['holiday_type'] === 'Regular' ? 'selected' : '' ?>>Regular (200%)</option>
                    <option value="Special" <?= $holiday['holiday_type'] === 'Special' ? 'selected' : '' ?>>Special (130%)</option>
                </select>
                <?php if (isset($errors['holiday_type'])): ?>
                    <span class="error-text"><?= htmlspecialchars($errors['holiday_type'], ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="Active"   <?= $holiday['status'] === 'Active'   ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $holiday['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/hr/schedules/holidays" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
