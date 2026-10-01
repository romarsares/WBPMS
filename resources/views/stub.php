<?php
use Wbpms\Http\View\Formatter;
/**
 * View: stub
 * Rendered by StubController for modules not yet implemented.
 *
 * @var string $stubTitle
 * @var string $stubDescription
 * @var string $base
 */
$stubTitle       = $stubTitle       ?? 'Module Coming Soon';
$stubDescription = $stubDescription ?? '';
?>
<div class="page-head">
    <div>
        <h1><?= Formatter::escape($stubTitle) ?></h1>
        <p><?= Formatter::escape($stubDescription) ?></p>
    </div>
</div>

<div class="panel" style="text-align:center;padding:60px 20px">
    <div style="font-size:48px;margin-bottom:16px">🚧</div>
    <h2 style="margin:0 0 10px"><?= Formatter::escape($stubTitle) ?></h2>
    <p style="color:var(--muted);max-width:480px;margin:0 auto 24px">
        This module is currently under development and will be available soon.
    </p>
    <a class="btn-secondary" href="<?= $base ?>/dashboard">← Back to Dashboard</a>
</div>
