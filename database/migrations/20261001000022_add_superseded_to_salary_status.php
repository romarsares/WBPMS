<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration: Add 'Superseded' to salary.status ENUM.
 *
 * Root cause (client issue #6):
 *   SalaryService::create() and SalaryService::update() both execute
 *   UPDATE salary SET status = 'Superseded' when closing the previous
 *   active salary row. However, the original migration
 *   (20260831000005_create_salary_payroll_contribution_tables.php) defined
 *   salary.status as enum('Active','Archived') — 'Superseded' was missing.
 *
 *   MySQL raised SQLSTATE[01000] Warning 1265: Data truncated for column
 *   'status' because 'Superseded' is not a valid ENUM member.
 *
 * Fix: ALTER the column to enum('Active','Superseded','Archived').
 *   - 'Superseded' is inserted between Active and Archived to keep logical order.
 *   - Existing rows are either 'Active' or 'Archived'; no data correction needed.
 *   - The down() method restores the original two-value ENUM.
 *     Note: any rows that had been silently truncated to '' will become
 *     invalid in the down path and should not exist in a clean DB.
 *
 * REQ054/REQ055 — SalaryService salary lifecycle.
 */
final class AddSupersededToSalaryStatus extends AbstractMigration
{
    public function up(): void
    {
        // ALTER COLUMN preserves existing valid values; 'Superseded' is added.
        $this->execute(
            "ALTER TABLE `salary`
             MODIFY COLUMN `status`
                ENUM('Active','Superseded','Archived')
                NOT NULL
                DEFAULT 'Active'
                COMMENT 'Active=current rate; Superseded=closed by a newer rate; Archived=manually archived'"
        );
    }

    public function down(): void
    {
        // Revert to original two-value ENUM.
        // Any 'Superseded' rows will be truncated — acceptable only when
        // rolling back in a development environment.
        $this->execute(
            "ALTER TABLE `salary`
             MODIFY COLUMN `status`
                ENUM('Active','Archived')
                NOT NULL
                DEFAULT 'Active'"
        );
    }
}
