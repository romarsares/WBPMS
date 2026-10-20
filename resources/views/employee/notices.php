<?php

use Wbpms\Http\View\Formatter;

/**
 * View: employee/notices  (GET /employee/notices)
 *
 * Variables injected by EmployeePortalController::notices():
 *   list<array{
 *     notice_id:int, title:string, body:string,
 *     issued_at:string, acknowledged_at:string|null,
 *     flag_type:string|null, triggering_date:string|null
 *   }> $notices
 *   string $base, $csrf
 */

$notices ??= [];

/**
 * Map a flag_type to a human-readable label shown on the notice card.
 */
$flagLabel = static function (?string $type): string {
    return match ($type) {
        'ConsecutiveLate'  => '3 Consecutive Lates',
        'TardinessMemoCap' => '3rd Tardiness Memo',
        'TwoWeekAbsence'   => '2-Week Absence',
        'ConsecutiveAWOL'  => '3 Consecutive AWOL',
        default            => '',
    };
};

/**
 * Classify a flag type as "Penalty Notice" or "Notice".
 * TardinessMemoCap, TwoWeekAbsence, and ConsecutiveAWOL trigger HR review
 * of a penalty (suspension / AWOL / termination policy).
 * ConsecutiveLate results in a memorandum notice only.
 */
$flagClass = static function (?string $type): string {
    return match ($type) {
        'TardinessMemoCap', 'TwoWeekAbsence', 'ConsecutiveAWOL' => 'Penalty Notice',
        'ConsecutiveLate' => 'Notice',
        default           => 'Notice',
    };
};

$flagClassColor = static function (?string $type): string {
    return match ($type) {
        'TardinessMemoCap', 'TwoWeekAbsence', 'ConsecutiveAWOL' => '#dc2626',
        default => '#b45309',
    };
};

$flagReason = static function (?string $type): string {
    return match ($type) {
        'ConsecutiveLate'  => 'You have recorded 3 consecutive late arrivals. A tardiness memorandum is required.',
        'TardinessMemoCap' => 'You have received 3 tardiness memoranda. HR is reviewing whether a one-week suspension applies.',
        'TwoWeekAbsence'   => 'You have not reported for 14 consecutive days. HR is reviewing this under the AWOL policy.',
        'ConsecutiveAWOL'  => 'You have 3 consecutive unexcused absences. HR is reviewing this under the AWOL/termination policy.',
        default            => '',
    };
};
?>

<div class="page-header">
    <div>
        <h1>HR Notices</h1>
        <p style="margin:0;color:#6b7280">Messages issued to you by Human Resources regarding your attendance records.</p>
    </div>
</div>

<?php if ($notices === []): ?>
    <div class="card" style="padding:1.5rem;color:#6b7280">You have no HR notices at this time.</div>
<?php else: ?>
    <div style="display:grid;gap:1rem">
        <?php foreach ($notices as $notice): ?>
            <?php
            $acknowledged  = ($notice['acknowledged_at'] ?? null) !== null;
            $flagType      = $notice['flag_type']       ?? null;
            $triggerDate   = $notice['triggering_date'] ?? null;
            $classification = $flagClass($flagType);
            $classColor     = $flagClassColor($flagType);
            $label          = $flagLabel($flagType);
            $reason         = $flagReason($flagType);
            ?>
            <article class="card" style="border-left:4px solid <?= $acknowledged ? '#6b7280' : $classColor ?>">
                <div style="display:flex;gap:1rem;justify-content:space-between;align-items:flex-start;flex-wrap:wrap">
                    <div>
                        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.3rem">
                            <h2 style="margin:0;font-size:1rem"><?= Formatter::escape((string) $notice['title']) ?></h2>
                            <?php if ($flagType !== null): ?>
                            <span style="background:<?= $classColor ?>;color:#fff;font-size:.7rem;font-weight:700;padding:1px 7px;border-radius:9999px;text-transform:uppercase;letter-spacing:.04em">
                                <?= Formatter::escape($classification) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <p style="margin:0;color:#6b7280;font-size:.8rem;line-height:1.5">
                            Issued <?= Formatter::date((string) $notice['issued_at']) ?>
                            <?php if ($label !== ''): ?>
                                &middot; <strong><?= Formatter::escape($label) ?></strong>
                            <?php endif; ?>
                            <?php if ($triggerDate !== null && $triggerDate !== ''): ?>
                                &middot; Triggered <?= Formatter::date($triggerDate) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="badge <?= $acknowledged ? 'badge-gray' : 'badge-yellow' ?>">
                        <?= $acknowledged ? 'Acknowledged' : 'Action required' ?>
                    </span>
                </div>

                <?php if ($reason !== '' && !$acknowledged): ?>
                <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:4px;padding:.5rem .75rem;margin-top:.75rem;font-size:.82rem;color:#78350f;line-height:1.5">
                    <?= Formatter::escape($reason) ?>
                </div>
                <?php endif; ?>

                <p style="white-space:pre-wrap;line-height:1.6;margin:1rem 0 .5rem;font-size:.9rem"><?= Formatter::escape((string) $notice['body']) ?></p>

                <?php if ($acknowledged): ?>
                    <p style="margin:0;color:#6b7280;font-size:.8rem">✔ Acknowledged <?= Formatter::date((string) $notice['acknowledged_at']) ?>.</p>
                <?php else: ?>
                    <form method="POST" action="<?= $base ?>/employee/notices/<?= (int) $notice['notice_id'] ?>/acknowledge" style="margin-top:.75rem">
                        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                        <button type="submit" class="btn btn-primary">Acknowledge this notice</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
