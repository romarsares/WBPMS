<?php

declare(strict_types=1);

namespace Wbpms\Domain\Payroll;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable value object representing one row from `holiday_calendar`.
 *
 * Carries the date, human-readable description, classification, and the
 * canonical pay_multiplier that matches the database CHECK constraint.
 * Construction validates that the supplied multiplier is consistent with
 * the type so domain objects can never hold an inconsistent holiday record.
 */
final readonly class HolidayRecord
{
    public function __construct(
        public DateTimeImmutable $date,
        public string            $description,
        public HolidayType       $type,
    ) {
        if (trim($description) === '') {
            throw new InvalidArgumentException('Holiday description must not be empty.');
        }
    }

    /**
     * The canonical pay_multiplier stored in holiday_calendar for this type.
     * Matches the CHECK constraint: Regular = 2.00, Special = 1.30.
     */
    public function payMultiplier(): float
    {
        return $this->type->workedMultiplier();
    }

    /**
     * Reconstruct from a holiday_calendar database row (associative array).
     *
     * @param array{
     *   holiday_date: string,
     *   description: string,
     *   holiday_type: string,
     * } $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            date: new DateTimeImmutable($row['holiday_date']),
            description: $row['description'],
            type: HolidayType::from($row['holiday_type']),
        );
    }
}
