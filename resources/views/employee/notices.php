<?php

use Wbpms\Http\View\Formatter;

/** @var list<array<string,mixed>> $notices */
$notices ??= [];
?>

<div class="page-header">
    <h1>HR Notices</h1>
    <p style="margin:0;color:#6b7280">Review messages issued to you by Human Resources.</p>
</div>

<?php if ($notices === []): ?>
    <div class="card" style="padding:1.5rem;color:#6b7280">You have no HR notices.</div>
<?php else: ?>
    <div style="display:grid;gap:1rem">
        <?php foreach ($notices as $notice): ?>
            <?php $acknowledged = ($notice['acknowledged_at'] ?? null) !== null; ?>
            <article class="card" style="border-left:4px solid <?= $acknowledged ? '#6b7280' : '#b45309' ?>">
                <div style="display:flex;gap:1rem;justify-content:space-between;align-items:flex-start">
                    <div>
                        <h2 style="margin:0 0 .3rem;font-size:1rem"><?= Formatter::escape((string) $notice['title']) ?></h2>
                        <p style="margin:0;color:#6b7280;font-size:.8rem">
                            Issued <?= Formatter::date((string) $notice['issued_at']) ?>
                            <?php if (($notice['flag_type'] ?? null) !== null): ?>
                                &middot; Attendance policy: <?= Formatter::escape((string) $notice['flag_type']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="badge <?= $acknowledged ? 'badge-gray' : 'badge-yellow' ?>">
                        <?= $acknowledged ? 'Acknowledged' : 'Action required' ?>
                    </span>
                </div>
                <p style="white-space:pre-wrap;line-height:1.55;margin:1rem 0"><?= Formatter::escape((string) $notice['body']) ?></p>
                <?php if ($acknowledged): ?>
                    <p style="margin:0;color:#6b7280;font-size:.8rem">Acknowledged <?= Formatter::date((string) $notice['acknowledged_at']) ?>.</p>
                <?php else: ?>
                    <form method="POST" action="<?= $base ?>/employee/notices/<?= (int) $notice['notice_id'] ?>/acknowledge">
                        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                        <button type="submit" class="btn btn-primary">Acknowledge notice</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
