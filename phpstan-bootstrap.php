<?php

declare(strict_types=1);

// PHPStan analyses application classes without executing the public front
// controller, where this runtime constant is normally declared.
define('APP_ROOT', __DIR__);
