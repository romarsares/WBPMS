<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use PDO;

/**
 * EmployeeDocumentRepository — PDO persistence for employee_document.
 *
 * All SQL lives here. The service owns the transaction boundary and
 * business rules; this repository only performs reads and writes.
 *
 * Task 4.6.5 / Requirement 14 AC4
 */
final class EmployeeDocumentRepository extends AbstractRepository
{
    /**
     * Insert a new document record and return its generated document_id.
     *
     * @param  array{
     *     employee_id:          int,
     *     document_type:        string,
     *     document_label:       string|null,
     *     original_filename:    string,
     *     stored_path:          string,
     *     mime_type:            string,
     *     byte_size:            int,
     *     sha256:               string,
     *     notes:                string|null,
     *     status:               string,
     *     replaces_document_id: int|null,
     *     uploaded_by:          int,
     * } $attributes
     */
    public function create(array $attributes): int
    {
        $now  = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $stmt = $this->pdo()->prepare(
            'INSERT INTO employee_document (
                employee_id, document_type, document_label,
                original_filename, stored_path, mime_type, byte_size, sha256,
                notes, status, replaces_document_id,
                uploaded_by, uploaded_at, created_at, updated_at
             ) VALUES (
                :employee_id, :document_type, :document_label,
                :original_filename, :stored_path, :mime_type, :byte_size, :sha256,
                :notes, :status, :replaces_document_id,
                :uploaded_by, :uploaded_at, :created_at, :updated_at
             )'
        );

        $stmt->execute([
            ':employee_id'          => $attributes['employee_id'],
            ':document_type'        => $attributes['document_type'],
            ':document_label'       => $attributes['document_label'] ?: null,
            ':original_filename'    => $attributes['original_filename'],
            ':stored_path'          => $attributes['stored_path'],
            ':mime_type'            => $attributes['mime_type'],
            ':byte_size'            => $attributes['byte_size'],
            ':sha256'               => $attributes['sha256'],
            ':notes'                => $attributes['notes'] ?: null,
            ':status'               => $attributes['status'],
            ':replaces_document_id' => $attributes['replaces_document_id'],
            ':uploaded_by'          => $attributes['uploaded_by'],
            ':uploaded_at'          => $now,
            ':created_at'           => $now,
            ':updated_at'           => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Return all documents for an employee, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function findByEmployee(int $employeeId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT
                d.document_id,
                d.employee_id,
                d.document_type,
                d.document_label,
                d.original_filename,
                d.stored_path,
                d.mime_type,
                d.byte_size,
                d.sha256,
                d.notes,
                d.status,
                d.replaces_document_id,
                d.uploaded_at,
                d.verified_at,
                d.archived_at,
                CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name,
                CONCAT(v.first_name, ' ', v.last_name) AS verified_by_name
             FROM employee_document d
             JOIN users up ON up.user_id = d.uploaded_by
             -- join to employee for uploader name
             LEFT JOIN employee u  ON u.employee_id  = up.employee_id
             LEFT JOIN users   vp  ON vp.user_id     = d.verified_by
             LEFT JOIN employee v  ON v.employee_id  = vp.employee_id
             WHERE d.employee_id = :employee_id
             ORDER BY d.uploaded_at DESC"
        );
        $stmt->execute([':employee_id' => $employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Return a single document row, or null if not found.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $documentId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT
                d.document_id,
                d.employee_id,
                d.document_type,
                d.document_label,
                d.original_filename,
                d.stored_path,
                d.mime_type,
                d.byte_size,
                d.sha256,
                d.notes,
                d.status,
                d.replaces_document_id,
                d.uploaded_by,
                d.uploaded_at,
                d.verified_by,
                d.verified_at,
                d.archived_at,
                CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name,
                CONCAT(v.first_name, ' ', v.last_name) AS verified_by_name
             FROM employee_document d
             JOIN users up ON up.user_id = d.uploaded_by
             LEFT JOIN employee u  ON u.employee_id  = up.employee_id
             LEFT JOIN users   vp  ON vp.user_id     = d.verified_by
             LEFT JOIN employee v  ON v.employee_id  = vp.employee_id
             WHERE d.document_id = :document_id"
        );
        $stmt->execute([':document_id' => $documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Mark a document as verified.
     */
    public function markVerified(int $documentId, int $verifiedByUserId): void
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->pdo()->prepare(
            'UPDATE employee_document
                SET verified_by = :verified_by,
                    verified_at = :verified_at,
                    updated_at  = :updated_at
              WHERE document_id = :document_id
                AND status      = \'Current\''
        )->execute([
            ':verified_by'  => $verifiedByUserId,
            ':verified_at'  => $now,
            ':updated_at'   => $now,
            ':document_id'  => $documentId,
        ]);
    }

    /**
     * Soft-archive a document.
     */
    public function archiveDocument(int $documentId): void
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->pdo()->prepare(
            "UPDATE employee_document
                SET status      = 'Archived',
                    archived_at = :archived_at,
                    updated_at  = :updated_at
              WHERE document_id = :document_id
                AND status     != 'Archived'"
        )->execute([
            ':archived_at'  => $now,
            ':updated_at'   => $now,
            ':document_id'  => $documentId,
        ]);
    }

    /**
     * Mark a document as Superseded (used when a replacement is uploaded).
     */
    public function markSuperseded(int $documentId): void
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->pdo()->prepare(
            "UPDATE employee_document
                SET status     = 'Superseded',
                    updated_at = :updated_at
              WHERE document_id = :document_id"
        )->execute([
            ':updated_at'  => $now,
            ':document_id' => $documentId,
        ]);
    }
}
