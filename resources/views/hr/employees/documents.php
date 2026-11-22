<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR — Employee document list and upload form.
 *
 * @var array{id:int, first_name:string, last_name:string, employee_number:string, status:string} $employee
 * @var list<array<string,mixed>>   $documents     All documents for this employee
 * @var array<string,string>        $documentTypes ENUM key → display label
 * @var array<string,string>        $errors        Validation errors (key: 'upload')
 * @var string                      $csrf
 * @var string                      $base
 */

$employee      ??= [];
$documents     ??= [];
$documentTypes ??= [];
$errors        ??= [];
$csrf          ??= '';
$page          ??= 1;
$totalPages    ??= 1;
$pageSize      ??= 50;
$total         ??= 0;

$empId   = (int) $employee['id'];
$empName = Formatter::escape($employee['last_name'] . ', ' . $employee['first_name']);

// Current and archived documents are shown directly. Superseded files remain
// available as audit history but start collapsed so a replacement does not
// look like a duplicate document.
$grouped = ['Current' => [], 'Superseded' => [], 'Archived' => []];
foreach ($documents as $doc) {
    $s = (string) ($doc['status'] ?? 'Current');
    $grouped[$s][] = $doc;
}

$statusBadge = static function (string $status): string {
    return match ($status) {
        'Current'    => '<span class="badge badge-warning">For verification</span>',
        'Superseded' => '<span class="badge badge-secondary">Replaced</span>',
        'Archived'   => '<span class="badge badge-secondary">Archived</span>',
        default      => Formatter::escape($status),
    };
};

$formatSize = static function (int $bytes): string {
    if ($bytes >= 1_048_576) {
        return number_format($bytes / 1_048_576, 1) . ' MB';
    }
    return number_format($bytes / 1024, 1) . ' KB';
};
?>

<div class="page-header">
    <h1>Documents — <?= $empName ?></h1>
    <a href="<?= $base ?>/hr/employees/<?= $empId ?>/edit" class="btn btn-secondary">← Back to Employee</a>
</div>

<?php if ($documents !== []): ?>
<div class="card" style="padding:.9rem 1rem;margin-bottom:1rem">
    <label for="document-search" style="display:block;font-size:.82rem;font-weight:600;margin-bottom:.35rem">Search documents</label>
    <input id="document-search" type="search" placeholder="Search type, label, filename, or status" style="max-width:420px;width:100%">
    <p id="document-search-empty" class="muted" hidden aria-live="polite" style="margin:.5rem 0 0">
        No documents match your search.
    </p>
</div>
<?php endif; ?>

<!-- ------------------------------------------------------------------ -->
<!-- Upload form                                                          -->
<!-- ------------------------------------------------------------------ -->
<div class="card" style="max-width:680px;margin-bottom:1.75rem">
    <h2 style="margin-top:0;font-size:1rem;font-weight:600">Upload New Document</h2>

    <?php if (isset($errors['upload'])): ?>
    <div class="flash flash-error" role="alert" style="margin-bottom:1rem">
        <?= Formatter::escape($errors['upload']) ?>
    </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= $base ?>/hr/employees/<?= $empId ?>/documents"
          enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="document_type">Document type <span style="color:#dc2626">*</span></label>
                <select id="document_type" name="document_type" required>
                    <option value="">— select —</option>
                    <?php foreach ($documentTypes as $key => $label): ?>
                    <option value="<?= Formatter::escape($key) ?>"
                        <?= (($_POST['document_type'] ?? '') === $key) ? 'selected' : '' ?>>
                        <?= Formatter::escape($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="document_label">Label</label>
                <input type="text" id="document_label" name="document_label"
                       value="<?= Formatter::escape((string) ($_POST['document_label'] ?? '')) ?>"
                       placeholder="e.g. SSS ID front, Contract 2026">
            </div>
        </div>

        <div class="form-group">
            <label for="doc_file">
                File <span style="color:#dc2626">*</span>
                <span class="muted" style="font-weight:400"> — PDF, JPEG, or PNG · max 5 MB</span>
            </label>
            <input type="file" id="doc_file" name="document"
                   accept=".pdf,.jpg,.jpeg,.png"
                   required
                   style="display:block;margin-top:.25rem">
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="2"
                      maxlength="1000"
                      style="width:100%;resize:vertical"><?= Formatter::escape((string) ($_POST['notes'] ?? '')) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Upload Document</button>
    </form>
</div>

<!-- ------------------------------------------------------------------ -->
<!-- Document list                                                        -->
<!-- ------------------------------------------------------------------ -->
<?php if ($documents === []): ?>
<p class="muted">No documents have been uploaded for this employee yet.</p>
<?php else: ?>

<?php foreach (['Current', 'Superseded', 'Archived'] as $section):
    if ($grouped[$section] === []) continue;
    $isReplacementHistory = $section === 'Superseded';
?>
<?php if ($isReplacementHistory): ?>
<details class="replacement-history" data-document-section style="margin-top:1.5rem">
    <summary style="cursor:pointer;font-size:.95rem;font-weight:600;color:#374151">
        Replacement History (<?= count($grouped[$section]) ?>)
    </summary>
    <p class="muted" style="margin:.45rem 0 .6rem;font-size:.82rem">
        Previous files are retained for audit, but hidden by default to avoid duplicate documents.
    </p>
<?php else: ?>
<section data-document-section>
<h2 style="font-size:.95rem;font-weight:600;color:#374151;margin-bottom:.5rem;margin-top:1.5rem">
    <?= $section . ' Documents' ?>
</h2>
<?php endif; ?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1rem">
    <table class="data-table document-table" style="margin:0">
        <thead>
            <tr>
                <th>Type</th>
                <th>Label / Filename</th>
                <th>Size</th>
                <th>Uploaded</th>
                <th>Status</th>
                <th style="width:1%">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($grouped[$section] as $doc):
            $docId       = (int) $doc['document_id'];
            $docStatus   = (string) $doc['status'];
            $isCurrent   = $docStatus === 'Current';
            $typeLabel   = $documentTypes[(string)($doc['document_type'] ?? '')] ?? (string) $doc['document_type'];
            $label       = (string) ($doc['document_label'] ?? '');
            $origName    = (string) $doc['original_filename'];
            $uploadedAt  = (string) $doc['uploaded_at'];
            $uploadedBy  = (string) ($doc['uploaded_by_name'] ?? '—');
            $verifiedAt  = $doc['verified_at'] ? (string) $doc['verified_at'] : null;
            $byteSize    = (int) $doc['byte_size'];
        ?>
        <tr data-document-row>
            <td><?= Formatter::escape($typeLabel) ?></td>
            <td>
                <?php if ($label !== ''): ?>
                <strong><?= Formatter::escape($label) ?></strong><br>
                <?php endif; ?>
                <span class="muted" style="font-size:.8rem"><?= Formatter::escape($origName) ?></span>
            </td>
            <td style="white-space:nowrap"><?= $formatSize($byteSize) ?></td>
            <td style="white-space:nowrap">
                <?= Formatter::escape(date('m/d/Y', strtotime($uploadedAt))) ?><br>
                <span class="muted" style="font-size:.8rem"><?= Formatter::escape($uploadedBy) ?></span>
            </td>
            <td>
                <?= ($docStatus === 'Current' && $verifiedAt)
                    ? '<span class="badge badge-success">Verified</span>'
                    : $statusBadge($docStatus) ?>
            </td>
            <td style="white-space:nowrap">
                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                    <!-- View / download -->
                    <a href="<?= $base ?>/hr/employees/<?= $empId ?>/documents/<?= $docId ?>"
                       class="btn btn-sm btn-secondary"
                       target="_blank"
                       title="View document">View</a>

                    <?php if ($isCurrent): ?>

                    <!-- Verify -->
                    <?php if (!$verifiedAt): ?>
                    <form method="POST"
                          action="<?= $base ?>/hr/employees/<?= $empId ?>/documents/<?= $docId ?>/verify"
                          onsubmit="return confirm('Mark this document as verified?')">
                        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-primary" title="Mark verified">
                            Verify
                        </button>
                    </form>
                    <?php endif; ?>

                    <!-- Replace (toggle inline form) -->
                    <button type="button"
                            class="btn btn-sm btn-warning"
                            onclick="toggleReplaceForm(<?= $docId ?>)"
                            title="Replace with new file">Replace</button>

                    <!-- Archive -->
                    <form method="POST"
                          action="<?= $base ?>/hr/employees/<?= $empId ?>/documents/<?= $docId ?>/archive"
                          onsubmit="return confirm('Archive this document? The file will be retained but marked inactive.')">
                        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" title="Archive document">
                            Archive
                        </button>
                    </form>

                    <?php endif; // isCurrent ?>
                </div>

                <?php if ($isCurrent): ?>
                <!-- Inline replace form (hidden by default) -->
                <div id="replace-form-<?= $docId ?>"
                     style="display:none;margin-top:.75rem;padding:.75rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px">
                    <p style="margin:0 0 .5rem;font-size:.85rem;font-weight:600">
                        Replace document — the current file will be kept as Superseded.
                    </p>
                    <form method="POST"
                          action="<?= $base ?>/hr/employees/<?= $empId ?>/documents/<?= $docId ?>/replace"
                          enctype="multipart/form-data">
                        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                        <div class="form-row" style="margin-bottom:.5rem">
                            <div class="form-group" style="margin:0">
                                <label style="font-size:.8rem">Document type <span style="color:#dc2626">*</span></label>
                                <select name="document_type" required style="font-size:.85rem">
                                    <?php foreach ($documentTypes as $key => $lbl): ?>
                                    <option value="<?= Formatter::escape($key) ?>"
                                        <?= ($key === (string)($doc['document_type'] ?? '')) ? 'selected' : '' ?>>
                                        <?= Formatter::escape($lbl) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin:0">
                                <label style="font-size:.8rem">Label</label>
                                <input type="text" name="document_label"
                                       value="<?= Formatter::escape($label) ?>"
                                       style="font-size:.85rem">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:.5rem">
                            <label style="font-size:.8rem">
                                New file <span style="color:#dc2626">*</span>
                                <span class="muted"> PDF / JPEG / PNG · max 5 MB</span>
                            </label>
                            <input type="file" name="document"
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                        </div>
                        <div class="form-group" style="margin-bottom:.75rem">
                            <label style="font-size:.8rem">Notes</label>
                            <textarea name="notes" rows="2" maxlength="1000"
                                      style="width:100%;font-size:.85rem;resize:vertical"></textarea>
                        </div>
                        <div style="display:flex;gap:.5rem">
                            <button type="submit" class="btn btn-sm btn-warning">Upload replacement</button>
                            <button type="button"
                                    class="btn btn-sm btn-secondary"
                                    onclick="toggleReplaceForm(<?= $docId ?>)">Cancel</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($isReplacementHistory): ?>
</details>
<?php else: ?>
</section>
<?php endif; ?>
<?php endforeach; ?>

<?php endif; ?>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="card" style="padding:1rem 1.25rem;margin-top:1rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem">
    <div style="font-size:.875rem;color:#374151">
        Showing
        <strong><?= (int) (($page - 1) * $pageSize + 1) ?></strong>
        to
        <strong><?= (int) min($page * $pageSize, $total) ?></strong>
        of
        <strong><?= (int) $total ?></strong>
        documents
    </div>
    <div style="display:flex;align-items:center;gap:.5rem;margin-left:auto">
        <label style="font-size:.8rem;font-weight:500;color:#374151">Per page</label>
        <select name="page_size" onchange="this.form.submit()" style="padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
            <option value="10" <?= $pageSize === 10 ? 'selected' : '' ?>>10</option>
            <option value="20" <?= $pageSize === 20 ? 'selected' : '' ?>>20</option>
            <option value="30" <?= $pageSize === 30 ? 'selected' : '' ?>>30</option>
            <option value="50" <?= $pageSize === 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $pageSize === 100 ? 'selected' : '' ?>>100</option>
        </select>
    </div>
    <nav aria-label="Documents pagination">
        <ul style="display:flex;gap:.25rem;list-style:none;margin:0;padding:0">
            <!-- Previous -->
            <?php if ($page > 1): ?>
            <li>
                <a href="<?= $base ?>/hr/employees/<?= $empId ?>/documents?page=<?= $page - 1 ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm" aria-label="Previous page">« Prev</a>
            </li>
            <?php else: ?>
            <li>
                <span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:not-allowed" aria-disabled="true">« Prev</span>
            </li>
            <?php endif; ?>

            <!-- Page numbers -->
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            if ($start > 1): ?>
            <li>
                <a href="<?= $base ?>/hr/employees/<?= $empId ?>/documents?page=1&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm">1</a>
            </li>
            <?php if ($start > 2): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
            <li>
                <?php if ($p === $page): ?>
                <span class="btn btn-primary btn-sm" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                <a href="<?= $base ?>/hr/employees/<?= $empId ?>/documents?page=<?= $p ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $p ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <li>
                <a href="<?= $base ?>/hr/employees/<?= $empId ?>/documents?page=<?= $totalPages ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $totalPages ?></a>
            </li>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
            <li>
                <a href="<?= $base ?>/hr/employees/<?= $empId ?>/documents?page=<?= $page + 1 ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm" aria-label="Next page">Next »</a>
            </li>
            <?php else: ?>
            <li>
                <span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:not-allowed" aria-disabled="true">Next »</span>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>

<script>
function toggleReplaceForm(docId) {
    var el = document.getElementById('replace-form-' + docId);
    if (el) {
        el.style.display = (el.style.display === 'none') ? 'block' : 'none';
    }
}

document.getElementById('document-search')?.addEventListener('input', function () {
    var query = this.value.toLowerCase().trim();
    var anyMatches = false;

    document.querySelectorAll('[data-document-section]').forEach(function (section) {
        var matchesInSection = 0;
        section.querySelectorAll('[data-document-row]').forEach(function (row) {
            var matches = !query || row.textContent.toLowerCase().includes(query);
            row.style.display = matches ? '' : 'none';
            if (matches) matchesInSection++;
        });

        section.hidden = query !== '' && matchesInSection === 0;
        if (matchesInSection > 0) anyMatches = true;

        // Search results in audit history must be visible even though the
        // history starts collapsed after a replacement.
        if (section.classList.contains('replacement-history')) {
            section.open = query !== '' && matchesInSection > 0;
        }
    });

    var empty = document.getElementById('document-search-empty');
    if (empty) empty.hidden = query === '' || anyMatches;
});
</script>

<style>
.badge {
    display: inline-block;
    padding: .15rem .5rem;
    border-radius: 9999px;
    font-size: .75rem;
    font-weight: 600;
    line-height: 1.4;
}
.badge-success   { background: #d1fae5; color: #065f46; }
.badge-warning   { background: #fef3c7; color: #92400e; }
.badge-secondary { background: #e5e7eb; color: #374151; }
.btn-sm {
    padding: .2rem .55rem;
    font-size: .8rem;
}
</style>
