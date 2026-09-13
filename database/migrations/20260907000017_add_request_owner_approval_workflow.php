<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Extends the request approval workflow so the Business Owner is the final
 * approver after the HR Head has pre-approved a request.
 *
 * New lifecycle:
 *   Pending  →  HR Head pre-approves  →  HRApproved
 *   HRApproved  →  Owner approves      →  Approved   (side-effects applied here)
 *   HRApproved  →  Owner returns       →  Returned   (back to HR for revision)
 *   Returned    →  HR re-submits       →  HRApproved (same pre-approve step)
 *
 * Any stage: HR or Owner can reject  →  Rejected
 * Pending / HRApproved / Returned: Employee or HR can cancel  →  Cancelled
 *
 * Adds:
 *   - request.status ENUM extended with 'HRApproved' and 'Returned'
 *   - request.owner_reviewed_by  FK → users (nullable)
 *   - request.owner_reviewed_at  DATETIME (nullable)
 *   - request.owner_notes        VARCHAR(1000) (nullable) — return/rejection note
 */
final class AddRequestOwnerApprovalWorkflow extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "ALTER TABLE request MODIFY COLUMN status
             ENUM('Pending','HRApproved','Approved','Rejected','Cancelled','Returned')
             NOT NULL DEFAULT 'Pending'"
        );

        $this->table('request')
            ->addColumn('owner_reviewed_by', 'biginteger', [
                'signed'  => false,
                'null'    => true,
                'default' => null,
                'after'   => 'review_notes',
            ])
            ->addColumn('owner_reviewed_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'after'   => 'owner_reviewed_by',
            ])
            ->addColumn('owner_notes', 'string', [
                'limit'   => 1000,
                'null'    => true,
                'default' => null,
                'after'   => 'owner_reviewed_at',
            ])
            ->addForeignKey('owner_reviewed_by', 'users', 'user_id', [
                'delete' => 'SET NULL',
                'update' => 'CASCADE',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('request')
            ->dropForeignKey('owner_reviewed_by')
            ->removeColumn('owner_reviewed_by')
            ->removeColumn('owner_reviewed_at')
            ->removeColumn('owner_notes')
            ->update();

        $this->execute(
            "ALTER TABLE request MODIFY COLUMN status
             ENUM('Pending','Approved','Rejected','Cancelled')
             NOT NULL DEFAULT 'Pending'"
        );
    }
}
