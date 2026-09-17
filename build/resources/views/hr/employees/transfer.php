<?php
/**
 * HR — Transfer employee to a new branch.
 *
 * @var array{id: int, employee_number: string, first_name: string, last_name: string, branch_name: string} $employee
 * @var list<array{id: int, name: string}> $branches
 * @var array<string, string>              $errors
 * @var string                             $csrf
 */
$empId   = (int) $employee['id'];
$empName = htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name'], ENT_QUOTES);
$empNum  = htmlspecialchars($employee['employee_number'] ?? '', ENT_QUOTES);
$current = htmlspecialchars($employee['branch_name'] ?? '—', ENT_QUOTES);
?>

<div class="page-header">
    <h1>Transfer Employee</h1>
    <a href="<?= $base ?>/hr/employees/<?= $empId ?>/edit" class="btn btn-secondary">← Back to Employee</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $field => $msg): ?>
            <p><?= htmlspecialchars($msg, ENT_QUOTES) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <strong><?= $empName ?></strong>
        <?php if ($empNum !== ''): ?>
            <span class="text-muted">(<?= $empNum ?>)</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p><strong>Current Branch:</strong> <?= $current ?></p>

        <form method="POST" action="<?= $base ?>/hr/employees/<?= $empId ?>/transfer">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

            <div class="form-group <?= isset($errors['branch_id']) ? 'has-error' : '' ?>">
                <label for="branch_id">New Branch <span class="required">*</span></label>
                <select name="branch_id" id="branch_id" class="form-control" required>
                    <option value="">— Select branch —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"
                            <?= (isset($_POST['branch_id']) && (int) $_POST['branch_id'] === (int) $b['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['branch_id'])): ?>
                    <span class="error-text"><?= htmlspecialchars($errors['branch_id'], ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['transfer_date']) ? 'has-error' : '' ?>">
                <label for="transfer_date">Transfer Date <span class="required">*</span></label>
                <input type="date"
                       name="transfer_date"
                       id="transfer_date"
                       class="form-control"
                       value="<?= htmlspecialchars($_POST['transfer_date'] ?? date('Y-m-d'), ENT_QUOTES) ?>"
                       required>
                <small class="form-hint">First day on the new branch. The current assignment will be closed the day before.</small>
                <?php if (isset($errors['transfer_date'])): ?>
                    <span class="error-text"><?= htmlspecialchars($errors['transfer_date'], ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Confirm Transfer</button>
                <a href="<?= $base ?>/hr/employees/<?= $empId ?>/edit" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
