<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the employee_document table.
 *
 * Implements Requirement 14 AC4 and the document capability described in
 * docs/employee-lifecycle-archive-rehire-spec.md §6.
 *
 * Files are stored in storage/private/ outside the web root.
 * The `stored_path` column holds the path relative to APP_ROOT.
 * Replacements retain history via `replaces_document_id` (self-reference).
 *
 * Task 4.6.5
 */
final class CreateEmployeeDocumentTable extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('employee_document')) {
            return;
        }

        $this->table('employee_document', [
            'id'          => false,
            'primary_key' => ['document_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Scanned employee documents — files stored in storage/private/',
        ])
            ->addColumn('document_id', 'biginteger', [
                'signed'   => false,
                'identity' => true,
                'null'     => false,
            ])
            ->addColumn('employee_id', 'biginteger', [
                'signed' => false,
                'null'   => false,
                'comment' => 'FK → employee',
            ])
            ->addColumn('document_type', 'enum', [
                'values'  => [
                    'IDPhoto',
                    'EmploymentContract',
                    'GovernmentID',
                    'TaxForm',
                    'BankProof',
                    'SeparationDocument',
                    'RehireDocument',
                    'Other',
                ],
                'null'    => false,
                'default' => 'Other',
            ])
            ->addColumn('document_label', 'string', [
                'limit'   => 200,
                'null'    => true,
                'default' => null,
                'comment' => 'Optional free-text label for display',
            ])
            ->addColumn('original_filename', 'string', [
                'limit'   => 260,
                'null'    => false,
                'comment' => 'Client-supplied filename (display only, never used for storage)',
            ])
            ->addColumn('stored_path', 'string', [
                'limit'   => 500,
                'null'    => false,
                'comment' => 'Path relative to APP_ROOT (never public)',
            ])
            ->addColumn('mime_type', 'string', [
                'limit'   => 100,
                'null'    => false,
            ])
            ->addColumn('byte_size', 'integer', [
                'signed' => false,
                'null'   => false,
            ])
            ->addColumn('sha256', 'string', [
                'limit'   => 64,
                'null'    => false,
                'comment' => 'Hex-encoded SHA-256 of the stored file',
            ])
            ->addColumn('notes', 'text', [
                'null'    => true,
                'default' => null,
            ])
            ->addColumn('status', 'enum', [
                'values'  => ['Current', 'Superseded', 'Archived'],
                'null'    => false,
                'default' => 'Current',
            ])
            ->addColumn('replaces_document_id', 'biginteger', [
                'signed'  => false,
                'null'    => true,
                'default' => null,
                'comment' => 'Self-reference — points to the document this one replaces',
            ])
            ->addColumn('uploaded_by', 'biginteger', [
                'signed' => false,
                'null'   => false,
                'comment' => 'FK → users',
            ])
            ->addColumn('uploaded_at', 'datetime', [
                'null'    => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('verified_by', 'biginteger', [
                'signed'  => false,
                'null'    => true,
                'default' => null,
                'comment' => 'FK → users; null until verified',
            ])
            ->addColumn('verified_at', 'datetime', [
                'null'    => true,
                'default' => null,
            ])
            ->addColumn('archived_at', 'datetime', [
                'null'    => true,
                'default' => null,
            ])
            ->addColumn('created_at', 'datetime', [
                'null'    => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('updated_at', 'datetime', [
                'null'    => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update'  => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['employee_id'], ['name' => 'idx_employee_document_employee'])
            ->addIndex(['status'],      ['name' => 'idx_employee_document_status'])
            ->addIndex(['sha256'],      ['name' => 'idx_employee_document_sha256'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', [
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('uploaded_by', 'users', 'user_id', [
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('verified_by', 'users', 'user_id', [
                'delete' => 'SET NULL',
                'update' => 'CASCADE',
            ])
            ->create();
    }

    public function down(): void
    {
        $this->table('employee_document')->drop()->save();
    }
}
