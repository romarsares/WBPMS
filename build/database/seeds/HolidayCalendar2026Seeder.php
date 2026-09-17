<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeder: 2026 Philippine Public Holidays
 *
 * Seeds the `holiday_calendar` table with official Regular and Special
 * Non-Working holidays for 2026, based on Proclamation No. 727 (s. 2025)
 * and prior RA 9492 / RA 10966 fixed-date holidays.
 *
 * ⚠  ADVISORY NOTICE ⚠
 * Presidential proclamations may add, move, or bridge holidays during the year.
 * HR must review and update this list against the official Official Gazette
 * before the first 2026 payroll run. This seeder provides the best-known
 * baseline at the time of development.
 *
 * Holiday types and multipliers enforced by DB CHECK constraint:
 *   Regular holiday  → pay_multiplier = 2.00 (worked: 200 %; absent: 100 %)
 *   Special holiday  → pay_multiplier = 1.30 (worked: 130 %; absent:   0 %)
 *
 * REQ047 | ADR-0001 §Holiday Pay
 */
class HolidayCalendar2026Seeder extends AbstractSeed
{
    /**
     * The 2026 Philippine holiday list.
     *
     * Each entry: [YYYY-MM-DD, 'Description', 'Regular'|'Special', multiplier]
     *
     * @return list<array{0:string,1:string,2:string,3:float}>
     */
    private function holidays(): array
    {
        return [
            // ---------------------------------------------------------------
            // REGULAR HOLIDAYS (RA 9492 fixed + Proclamation 727 s.2025)
            // ---------------------------------------------------------------
            ['2026-01-01', "New Year's Day",                          'Regular', 2.00],
            ['2026-04-02', 'Maundy Thursday',                         'Regular', 2.00],
            ['2026-04-03', 'Good Friday',                             'Regular', 2.00],
            ['2026-04-09', 'Araw ng Kagitingan (Day of Valor)',       'Regular', 2.00],
            ['2026-05-01', 'Labor Day',                               'Regular', 2.00],
            ['2026-06-12', 'Independence Day',                        'Regular', 2.00],
            ['2026-08-31', 'National Heroes Day',                     'Regular', 2.00],
            ['2026-11-30', 'Bonifacio Day',                           'Regular', 2.00],
            ['2026-12-25', 'Christmas Day',                           'Regular', 2.00],
            ['2026-12-30', 'Rizal Day',                               'Regular', 2.00],

            // Eid'l Fitr — approximate date based on Islamic calendar for 2026;
            // actual date to be confirmed by proclamation when the moon is sighted.
            ['2026-03-20', "Eid'l Fitr (approx.)",                    'Regular', 2.00],

            // Eid'l Adha — approximate date; confirm per proclamation.
            ['2026-05-27', "Eid'l Adha (approx.)",                    'Regular', 2.00],

            // ---------------------------------------------------------------
            // SPECIAL NON-WORKING HOLIDAYS (Proclamation 727 s.2025 + fixed)
            // ---------------------------------------------------------------
            ['2026-01-02', 'Special Non-Working Day (New Year bridge)', 'Special', 1.30],
            ['2026-02-05', 'Chinese New Year (Year of the Horse)',       'Special', 1.30],
            ['2026-04-04', 'Black Saturday',                             'Special', 1.30],
            ['2026-08-21', 'Ninoy Aquino Day',                          'Special', 1.30],
            ['2026-10-31', "All Hallows' Eve (Halloween)",               'Special', 1.30],
            ['2026-11-01', "All Saints' Day",                            'Special', 1.30],
            ['2026-11-02', "All Souls' Day",                             'Special', 1.30],
            ['2026-12-08', 'Feast of the Immaculate Conception',        'Special', 1.30],
            ['2026-12-24', 'Christmas Eve',                              'Special', 1.30],
            ['2026-12-31', "New Year's Eve",                             'Special', 1.30],
        ];
    }

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($this->holidays() as [$date, $description, $type, $multiplier]) {
            // Idempotent: skip if this date already exists (unique index on holiday_date)
            $exists = $this->fetchRow(
                "SELECT holiday_id FROM holiday_calendar WHERE holiday_date = '{$date}'"
            );

            if ($exists !== false) {
                continue;
            }

            // Escape description for safe inline insertion
            $safeDescription = str_replace("'", "\\'", $description);

            $this->execute("
                INSERT INTO holiday_calendar
                    (holiday_date, description, holiday_type, pay_multiplier, status, created_at, updated_at)
                VALUES
                    ('{$date}', '{$safeDescription}', '{$type}', {$multiplier}, 'Active', '{$now}', '{$now}')
            ");
        }
    }
}
