<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

use DateTimeZone;
use InvalidArgumentException;

final readonly class ParserContext
{
    public const DEFAULT_MAX_ROWS = 5000;
    public const DEFAULT_MAX_DATE_COLUMNS = 31;
    public const DEFAULT_MAX_TOKENS_PER_CELL = 16;
    public const DEFAULT_MAX_TOTAL_TOKENS = 50000;

    public function __construct(
        public int $deviceId,
        public int $sourceYear,
        public int $sourceMonth,
        public DateTimeZone $timezone = new DateTimeZone('Asia/Manila'),
        public int $maxRows = self::DEFAULT_MAX_ROWS,
        public int $maxDateColumns = self::DEFAULT_MAX_DATE_COLUMNS,
        public int $maxTokensPerCell = self::DEFAULT_MAX_TOKENS_PER_CELL,
        public int $maxTotalTokens = self::DEFAULT_MAX_TOTAL_TOKENS,
    ) {
        if ($deviceId < 1 || $sourceMonth < 1 || $sourceMonth > 12 || $sourceYear < 2000) {
            throw new InvalidArgumentException('Invalid attendance parser context.');
        }
    }
}
