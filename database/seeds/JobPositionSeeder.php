<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * JobPositionSeeder — pre-loads all known job positions for Light Diamond Enterprises.
 *
 * Source: Capstone 1 Final Submission — Project Context (page 2–3):
 *   Banga branch  : Store In-charge, Sales Clerk, Cashier, Checker, Encoder,
 *                   Driver, Laborer, Secretary, HR Head, Warehouse In-charge
 *   Surallah branch: Sales Clerk, Cashier, Checker, Encoder
 *
 * Additional standard positions added for completeness:
 *   Business Owner, Accounting Staff
 *
 * Run order: no dependencies (no FK to other tables).
 * Idempotent: skips rows where position_title already exists.
 */
class JobPositionSeeder extends AbstractSeed
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // [position_title, department, sort_order]
        $positions = [
            // Management / Admin
            ['Business Owner',      'Management',   1],
            ['Store In-charge',     'Management',   2],
            ['HR Head',             'Admin',        3],
            ['Secretary',           'Admin',        4],
            ['Accounting Staff',    'Admin',        5],

            // Operations / Sales
            ['Sales Clerk',         'Sales',        10],
            ['Cashier',             'Sales',        11],
            ['Checker',             'Operations',   12],
            ['Encoder',             'Operations',   13],

            // Warehouse / Logistics
            ['Warehouse In-charge', 'Warehouse',    20],
            ['Driver',              'Logistics',    21],
            ['Laborer',             'Warehouse',    22],
        ];

        foreach ($positions as [$title, $dept, $sort]) {
            $exists = $this->fetchRow(
                "SELECT position_id FROM job_position WHERE position_title = '" .
                addslashes($title) . "'"
            );

            if ($exists !== false) {
                continue; // already seeded
            }

            $this->execute(
                "INSERT INTO job_position
                    (position_title, department, status, sort_order, created_at, updated_at)
                 VALUES
                    ('" . addslashes($title) . "',
                     '" . addslashes($dept)  . "',
                     'Active', {$sort},
                     '{$now}', '{$now}')"
            );
        }
    }
}
