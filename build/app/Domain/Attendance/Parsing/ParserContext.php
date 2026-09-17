<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

use DateTimeZone;
use InvalidArgumentException;

final class ParserContext
{
    public const DEFAULT_MAX_ROWS              = 5000;
    public const DEFAULT_MAX_DATE_COLUMNS      = 31;
    public const DEFAULT_MAX_TOKENS_PER_CELL   = 16;
    public const DEFAULT_MAX_TOTAL_TOKENS      = 50000;

    public int $deviceId;
    public int $sourceYear;
    public int $sourceMonth;
    public DateTimeZone $timezone;
    public int $maxRows;
    public int $maxDateColumns;
    public int $maxTokensPerCell;
    public int $maxTotalTokens;

    public function __construct(
        int $deviceId,
        int $sourceYear,
        int $sourceMonth,
        ?DateTimeZone $timezone          = null,
        int $maxRows                     = self::DEFAULT_MAX_ROWS,
        int $maxDateColumns              = self::DEFAULT_MAX_DATE_COLUMNS,
        int $maxTokensPerCell            = self::DEFAULT_MAX_TOKENS_PER_CELL,
        int $maxTotalTokens              = self::DEFAULT_MAX_TOTAL_TOKENS
    ) {
        if ($deviceId < 1 || $sourceMonth < 1 || $sourceMonth > 12 || $sourceYear < 2000) {
            throw new InvalidArgumentException('Invalid attendance parser context.');
        }

        $this->deviceId          = $deviceId;
        $this->sourceYear        = $sourceYear;
        $this->sourceMonth       = $sourceMonth;
        $this->timezone          = $timezone ?? new DateTimeZone('Asia/Manila');
        $this->maxRows           = $maxRows;
        $this->maxDateColumns    = $maxDateColumns;
        $this->maxTokensPerCell  = $maxTokensPerCell;
        $this->maxTotalTokens    = $maxTotalTokens;
    }
}
