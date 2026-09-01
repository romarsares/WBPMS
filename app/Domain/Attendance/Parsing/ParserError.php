<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

/** A safe, deterministic validation error suitable for an HR upload screen. */
final class ParserError
{
    public function __construct(
        public string $code,
        public string $message,
        public ?int $row = null,
        public ?string $column = null,
    ) {
    }
}
