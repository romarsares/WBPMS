<?php

declare(strict_types=1);

namespace Wbpms\Http\View;

use DateTimeInterface;

final class Formatter
{
    public static function date(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $date = is_string($value) ? new \DateTimeImmutable($value) : $value;
        return $date->format('m/d/y');
    }

    public static function time(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $date = is_string($value) ? new \DateTimeImmutable($value) : $value;
        return $date->format('H:i');
    }

    public static function escape(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
