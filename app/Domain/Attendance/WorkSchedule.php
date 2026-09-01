<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** A single effective work schedule; overlap validation is a persistence concern. */
final class WorkSchedule
{
    public function __construct(
        public int $scheduleId,
        public string $startTime,
        public string $endTime,
        public int $unpaidBreakMinutes = 60,
        public int $standardMinutes = 480,
    ) {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime)
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $endTime)
            || $unpaidBreakMinutes < 0
            || $standardMinutes < 1) {
            throw new InvalidArgumentException('Invalid work schedule.');
        }
    }

    public function startOn(DateTimeImmutable $date, DateTimeZone $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d') . " {$this->startTime}", $timezone);
    }

    public function endOn(DateTimeImmutable $date, DateTimeZone $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d') . " {$this->endTime}", $timezone);
    }
}
