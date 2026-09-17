<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 001: Identity, Access, and Audit tables.
 *
 * Creates: role, users, sessions, audit_logs
 *
 * ADR-0002 §9: MySQL-persisted sessions are part of the schema contract.
 * ADR-0002 §10: Audit events may have no authenticated user_id.
 * Canonical schema v1.1: Identity, access, and audit section.
 */
final class CreateIdentityAccessTables extends AbstractMigration
{
    public function up(): void
    {
        // -------------------------------------------------------------------
        // role
        // -------------------------------------------------------------------
        $role = $this->table('role', [
            'id'          => false,
            'primary_key' => ['role_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Application roles: BusinessOwner, HRHead, Employee',
        ]);
        $role
            ->addColumn('role_id', 'biginteger', [
                'signed'        => false,
                'identity'      => true,
                'null'          => false,
            ])
            ->addColumn('role_name', 'string', [
                'limit'   => 30,
                'null'    => false,
                'comment' => 'BusinessOwner | HRHead | Employee',
            ])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['role_name'], ['unique' => true, 'name' => 'uq_role_name'])
            ->create();

        // -------------------------------------------------------------------
        // users
        // -------------------------------------------------------------------
        $users = $this->table('users', [
            'id'          => false,
            'primary_key' => ['user_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'System accounts; employee_id nullable for the Business Owner',
        ]);
        $users
            ->addColumn('user_id', 'biginteger', [
                'signed'   => false,
                'identity' => true,
                'null'     => false,
            ])
            ->addColumn('employee_id', 'biginteger', [
                'signed'  => false,
                'null'    => true,
                'default' => null,
                'comment' => 'NULL for non-payroll users (e.g. Business Owner)',
            ])
            ->addColumn('role_id', 'biginteger', [
                'signed' => false,
                'null'   => false,
            ])
            ->addColumn('username', 'string', [
                'limit' => 50,
                'null'  => false,
            ])
            ->addColumn('account_email', 'string', [
                'limit'   => 254,
                'null'    => false,
                'comment' => 'Account/recovery email; may differ from employee.email',
            ])
            ->addColumn('password_hash', 'string', [
                'limit' => 255,
                'null'  => false,
            ])
            ->addColumn('status', 'enum', [
                'values'  => ['Active', 'Inactive', 'Archived'],
                'null'    => false,
                'default' => 'Active',
            ])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id'], ['unique' => true, 'name' => 'uq_users_employee_id'])
            ->addIndex(['username'],    ['unique' => true, 'name' => 'uq_users_username'])
            ->addIndex(['account_email'], ['unique' => true, 'name' => 'uq_users_account_email'])
            ->addForeignKey('role_id', 'role', 'role_id', [
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ])
            ->create();

        // employee FK will be added in migration 002 after the employee table exists.

        // -------------------------------------------------------------------
        // sessions
        // -------------------------------------------------------------------
        $sessions = $this->table('sessions', [
            'id'          => false,
            'primary_key' => ['session_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'PHP session storage; managed by DatabaseSessionHandler',
        ]);
        $sessions
            ->addColumn('session_id', 'string', [
                'limit'   => 128,
                'null'    => false,
                'comment' => 'PHP session_id()',
            ])
            ->addColumn('expires_at', 'biginteger', [
                'signed'  => false,
                'null'    => false,
                'comment' => 'Unix timestamp; application enforces idle+absolute expiry',
            ])
            ->addColumn('data', 'text', [
                'limit'   => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM,
                'null'    => false,
                'comment' => 'Serialized session payload',
            ])
            ->addIndex(['expires_at'], ['name' => 'idx_sessions_expires_at'])
            ->create();

        // -------------------------------------------------------------------
        // audit_logs
        // -------------------------------------------------------------------
        $audit = $this->table('audit_logs', [
            'id'          => false,
            'primary_key' => ['log_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Immutable audit trail; user_id NULL for unauthenticated events',
        ]);
        $audit
            ->addColumn('log_id', 'biginteger', [
                'signed'   => false,
                'identity' => true,
                'null'     => false,
            ])
            ->addColumn('user_id', 'biginteger', [
                'signed'  => false,
                'null'    => true,
                'default' => null,
                'comment' => 'NULL for failed-login and pre-auth events',
            ])
            ->addColumn('event_type', 'string', [
                'limit'   => 50,
                'null'    => false,
                'comment' => 'e.g. login_success, login_failure, payroll_approved',
            ])
            ->addColumn('action_performed', 'string', [
                'limit' => 100,
                'null'  => false,
            ])
            ->addColumn('table_affected', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
            ])
            ->addColumn('record_id', 'biginteger', [
                'signed'  => false,
                'null'    => true,
                'default' => null,
            ])
            ->addColumn('attempted_identifier', 'string', [
                'limit'   => 254,
                'null'    => true,
                'default' => null,
                'comment' => 'Username or email attempted during failed login',
            ])
            ->addColumn('request_id', 'char', [
                'limit'   => 36,
                'null'    => true,
                'default' => null,
                'comment' => 'UUID from X-Request-ID header',
            ])
            ->addColumn('ip_address', 'string', [
                'limit'   => 45,
                'null'    => true,
                'default' => null,
                'comment' => 'IPv4 or IPv6',
            ])
            ->addColumn('user_agent', 'string', [
                'limit'   => 500,
                'null'    => true,
                'default' => null,
            ])
            ->addColumn('description', 'string', [
                'limit'   => 1000,
                'null'    => true,
                'default' => null,
            ])
            ->addColumn('action_at', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'action_at'], ['name' => 'idx_audit_user_action_at'])
            ->addIndex(['event_type', 'action_at'], ['name' => 'idx_audit_event_action_at'])
            ->addIndex(['request_id'], ['name' => 'idx_audit_request_id'])
            ->addForeignKey('user_id', 'users', 'user_id', [
                'delete' => 'SET NULL',
                'update' => 'CASCADE',
            ])
            ->create();
    }

    public function down(): void
    {
        $this->table('audit_logs')->drop()->save();
        $this->table('sessions')->drop()->save();
        $this->table('users')->drop()->save();
        $this->table('role')->drop()->save();
    }
}
