<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Allows one login account to hold several application roles while retaining
 * one unique email address, password, status, and verification state.
 */
final class AddUserRoleMemberships extends AbstractMigration
{
    public function up(): void
    {
        $this->table('user_role', [
            'id'          => false,
            'primary_key' => ['user_id', 'role_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Role grants for each unique system account',
        ])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['role_id'], ['name' => 'idx_user_role_role'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('role_id', 'role', 'role_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // Preserve every existing account's current role as its first grant.
        $this->execute(
            'INSERT INTO user_role (user_id, role_id)\n'
            . 'SELECT user_id, role_id FROM users\n'
            . 'ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)'
        );
    }

    public function down(): void
    {
        $this->table('user_role')->drop()->save();
    }
}
