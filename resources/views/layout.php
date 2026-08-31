<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/** @var string $title */
/** @var string $content */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Formatter::escape($title) ?> · WBPMS</title>
</head>
<body>
    <header><a href="/">WBPMS</a></header>
    <main><?= $content ?></main>
</body>
</html>
