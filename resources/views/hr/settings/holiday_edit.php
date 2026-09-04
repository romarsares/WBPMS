<?php
declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR Settings — Edit Holiday.
 *
 * @var array{holiday_id: int, holiday_date: string, description: string, holiday_type: string, status: string} $holiday
 * @var array<string, string> $errors
 * @var string                $csrf
 * @var string                $base
 */
$id = (int) $holiday['holiday_id'];
?>

<!-- page-head matches the hub style -->
<div class="page-head">
    <div>
        <h1>Edit Holiday</h1>
        <p>Update the details of this holiday entry.</p>
    </div>
    <a href="<?= $base ?>/hr/settings/holidays" class="btn btn-secondary">← Back to Holidays</a>
</div>

<div style="max-width:520px">
    <div class="card">

        <?php if (!empty($errors)): ?>
        <div class="alert error" role="alert" style="margin-bottom:1rem">
            <?php foreach ($errors as $msg): ?>
                <p style="margin:.1rem 0"><?= Formatter::escape((string) $msg) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $base ?>/hr/settings/holidays/<?= $id ?>">
            <input type="hidden" name="_csrf"   value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_method" value="PUT">

            <div class="form-group" style="margin-bottom:.85rem">
                <label for="holiday_date">Date <span style="color:#dc2626">*</span></label>
                <input type="date" name="holiday_date" id="holiday_date" class="form-control
                    <?= isset($errors['holiday_date']) ? ' is-invalid' : '' ?>"
                    value="<?= Formatter::escape($holiday['holiday_date']) ?>" required>
                <?php if (isset($errors['holiday_date'])): ?>
                    <p class="field-error" style="color:#dc2626;font-size:.8rem;margin:.2rem 0 0">
                        <?= Formatter::escape($errors['holiday_date']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom:.85rem">
                <label for="description">Name / Description <span style="color:#dc2626">*</span></label>
                <input type="text" name="description" id="description" class="form-control
                    <?= isset($errors['description']) ? ' is-invalid' : '' ?>"
                    value="<?= Formatter::escape($holiday['description']) ?>"
                    maxlength="100" required>
                <?php if (isset($errors['description'])): ?>
                    <p class="field-error" style="color:#dc2626;font-size:.8rem;margin:.2rem 0 0">
                        <?= Formatter::escape($errors['description']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom:.85rem">
                <label for="holiday_type">Type <span style="color:#dc2626">*</span></label>
                <select name="holiday_type" id="holiday_type" class="form-control
                    <?= isset($errors['holiday_type']) ? ' is-invalid' : '' ?>" required>
                    <option value="Regular" <?= $holiday['holiday_type'] === 'Regular' ? 'selected' : '' ?>>Regular (200%)</option>
                    <option value="Special" <?= $holiday['holiday_type'] === 'Special' ? 'selected' : '' ?>>Special (130%)</option>
                </select>
                <?php if (isset($errors['holiday_type'])): ?>
                    <p class="field-error" style="color:#dc2626;font-size:.8rem;margin:.2rem 0 0">
                        <?= Formatter::escape($errors['holiday_type']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="Active"   <?= $holiday['status'] === 'Active'   ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $holiday['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <a href="<?= $base ?>/hr/settings/holidays" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
