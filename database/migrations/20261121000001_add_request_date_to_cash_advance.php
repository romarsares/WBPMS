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
        // Add request_date column to cash_advance_request_detail
        $this->table('cash_advance_request_detail')
            ->addColumn('request_date', 'date', ['null' => false, 'after' => 'amount'])
            ->save();

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