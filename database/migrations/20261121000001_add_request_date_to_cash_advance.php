<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration: Add request_date to cash_advance_request_detail
 *
 * The Cash Advance request form needs a date field to track when the
 * advance is requested/needed. This enables validation of weekly limits
 * (₱500/week repayment capacity per ADR-0001).
 */
final class AddRequestDateToCashAdvance extends AbstractMigration
{
    public function up(): void
    {
        // Add as nullable first: existing rows would otherwise get '0000-00-00',
        // which strict-mode MySQL rejects and crashes the deploy.
        $this->table('cash_advance_request_detail')
            ->addColumn('request_date', 'date', ['null' => true, 'default' => null, 'after' => 'amount'])
            ->save();

        // Backfill existing rows from the parent request's creation date
        $this->execute(
            'UPDATE cash_advance_request_detail d '
            . 'JOIN request r ON r.request_id = d.request_id '
            . 'SET d.request_date = DATE(r.created_at) '
            . 'WHERE d.request_date IS NULL'
        );

        $this->execute('ALTER TABLE cash_advance_request_detail MODIFY request_date DATE NOT NULL');

        // Add index for efficient weekly queries
        $this->table('cash_advance_request_detail')
            ->addIndex(['request_date'], ['name' => 'idx_card_request_date'])
            ->save();
    }

    public function down(): void
    {
        $this->table('cash_advance_request_detail')
            ->removeColumn('request_date')
            ->save();
    }
}