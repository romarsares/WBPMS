<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add user_id column to sessions table for single-active-session enforcement.
 *
 * Allows the application to locate and invalidate all existing sessions
 * for a given user when a new login is initiated, ensuring only one active
 * session exists per account at any time.
 *
 * The column is nullable so that anonymous/pre-auth sessions (e.g. a session
 * carrying only a CSRF token before the user logs in) are unaffected.
 *
 * An index on user_id makes the DELETE-by-user lookup fast.
 */
final class AddUserIdToSessions extends AbstractMigration
{
    public function up(): void
    {
        $this->table('sessions')
            ->addColumn('user_id', 'biginteger', [
                'signed'  => false,
                'null'    => true,
                'default' => null,
                'after'   => 'session_id',
                'comment' => 'FK to users.user_id; NULL for anonymous sessions',
            ])
            ->addIndex(['user_id'], ['name' => 'idx_sessions_user_id'])
            ->save();
    }

    public function down(): void
    {
        $this->table('sessions')
            ->removeIndex(['user_id'])
            ->removeColumn('user_id')
            ->save();
    }
}