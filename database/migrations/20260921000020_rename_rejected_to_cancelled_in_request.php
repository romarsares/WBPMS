<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration: rename Rejected → Cancelled in request.status enum
 *
 * Background: HR interview 2026-09-21 confirmed that the `Rejected` status
 * does not exist in the business process. All declined requests use `Cancelled`
 * regardless of who (HR Head or Business Owner) declined them.
 *
 * This migration:
 *  1. Updates any existing `Rejected` rows to `Cancelled`.
 *  2. Modifies the status column enum to remove `Rejected` and ensure
 *     `Cancelled` is present.
 */
final class RenameRejectedToCancelledInRequest extends AbstractMigration
{
    public function up(): void
    {
        // Step 1: Migrate any existing Rejected rows to Cancelled
        $this->execute(
            "UPDATE `request` SET `status` = 'Cancelled' WHERE `status` = 'Rejected'"
        );

        // Step 2: Alter the enum to remove Rejected, keep Cancelled
        // Workflow statuses: Pending → HRApproved → Approved | Returned | Cancelled
        $this->execute(
            "ALTER TABLE `request`
             MODIFY COLUMN `status` ENUM(
                 'Pending',
                 'HRApproved',
                 'Approved',
                 'Returned',
                 'Cancelled'
             ) NOT NULL DEFAULT 'Pending'"
        );
    }

    public function down(): void
    {
        // Restore Rejected to the enum (data cannot be recovered — down() is
        // for schema rollback only; any Cancelled rows that were originally
        // Rejected will remain as Cancelled after rollback)
        $this->execute(
            "ALTER TABLE `request`
             MODIFY COLUMN `status` ENUM(
                 'Pending',
                 'HRApproved',
                 'Approved',
                 'Returned',
                 'Rejected',
                 'Cancelled'
             ) NOT NULL DEFAULT 'Pending'"
        );
    }
}
