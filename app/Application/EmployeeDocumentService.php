<?php

declare(strict_types=1);

namespace Wbpms\Application;

use PDO;
use RuntimeException;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\EmployeeDocumentRepository;

/**
 * EmployeeDocumentService — validates, stores, and manages employee documents.
 *
 * Owns:
 *   - File type/size/content-signature validation
 *   - Randomised storage path generation under storage/private/
 *   - SHA-256 checksum recording
 *   - Transaction boundary for DB insert + audit log
 *   - Temp-file cleanup on any failure
 *
 * The repository owns SQL; this service owns business rules.
 *
 * Requirement 14 AC4 / Task 4.6.5
 */
final class EmployeeDocumentService
{
    /** Allowed MIME types mapped to their magic-byte signatures. */
    private const ALLOWED_TYPES = [
        'application/pdf' => ["\x25\x50\x44\x46"],          // %PDF
        'image/jpeg'      => ["\xFF\xD8\xFF"],               // JFIF / EXIF
        'image/png'       => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"], // PNG
    ];

    /** Maximum allowed file size in bytes (5 MB). */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** Valid document_type ENUM values. */
    public const DOCUMENT_TYPES = [
        'IDPhoto'             => 'ID Photo',
        'EmploymentContract'  => 'Employment Contract',
        'GovernmentID'        => 'Government ID',
        'TaxForm'             => 'Tax Form',
        'BankProof'           => 'Bank Proof',
        'SeparationDocument'  => 'Separation Document',
        'RehireDocument'      => 'Rehire Document',
        'Other'               => 'Other',
    ];

    public function __construct(private readonly Connection $connection) {}

    // -----------------------------------------------------------------------
    // Public operations
    // -----------------------------------------------------------------------

    /**
     * Upload a new document for an employee.
     *
     * @param  int                    $employeeId
     * @param  array<string, mixed>   $uploadedFile  Entry from $_FILES['document']
     * @param  array{
     *     document_type:  string,
     *     document_label: string,
     *     notes:          string,
     * }                              $meta
     * @param  int                    $actorId       Authenticated HR user_id
     * @return int                    New document_id
     * @throws RuntimeException       on validation or storage failure
     */
    public function upload(
        int   $employeeId,
        array $uploadedFile,
        array $meta,
        int   $actorId
    ): int {
        $this->guardUploadError($uploadedFile);
        $tmpPath = (string) $uploadedFile['tmp_name'];

        $this->validateFile($tmpPath, (string) $uploadedFile['name'], (int) $uploadedFile['size']);
        $this->validateMeta($meta);

        $mimeType = $this->detectMime($tmpPath);
        $ext      = $this->safeExtension($mimeType);
        $sha256   = hash_file('sha256', $tmpPath);

        if ($sha256 === false) {
            throw new RuntimeException('Could not compute file checksum.');
        }

        $storedPath = $this->generateStoredPath($employeeId, $ext);
        $absPath    = APP_ROOT . '/' . $storedPath;

        $this->ensureDirectory(dirname($absPath));

        if (!move_uploaded_file($tmpPath, $absPath)) {
            throw new RuntimeException('Failed to move uploaded file to secure storage.');
        }

        try {
            return $this->persistDocument(
                employeeId:        $employeeId,
                documentType:      (string) $meta['document_type'],
                documentLabel:     (string) ($meta['document_label'] ?? ''),
                originalFilename:  (string) $uploadedFile['name'],
                storedPath:        $storedPath,
                mimeType:          $mimeType,
                byteSize:          (int) $uploadedFile['size'],
                sha256:            $sha256,
                notes:             (string) ($meta['notes'] ?? ''),
                status:            'Current',
                replacesDocumentId: null,
                uploadedBy:        $actorId,
                auditDescription:  'upload_document',
            );
        } catch (\Throwable $e) {
            // Roll back the file move on DB failure
            @unlink($absPath);
            throw $e;
        }
    }

    /**
     * Replace an existing document with a new upload.
     *
     * The prior document is marked Superseded; its file is retained on disk.
     *
     * @param  array<string, mixed>   $uploadedFile  Entry from $_FILES['document']
     * @param  array{
     *     document_type:  string,
     *     document_label: string,
     *     notes:          string,
     * }                              $meta
     * @throws RuntimeException
     */
    public function replace(
        int   $documentId,
        int   $employeeId,
        array $uploadedFile,
        array $meta,
        int   $actorId
    ): int {
        $repo     = new EmployeeDocumentRepository($this->connection);
        $existing = $repo->findById($documentId);

        if ($existing === null) {
            throw new RuntimeException('Document not found.');
        }
        if ((int) $existing['employee_id'] !== $employeeId) {
            throw new RuntimeException('Document does not belong to this employee.');
        }
        if ((string) $existing['status'] === 'Archived') {
            throw new RuntimeException('An archived document cannot be replaced.');
        }

        $this->guardUploadError($uploadedFile);
        $tmpPath = (string) $uploadedFile['tmp_name'];

        $this->validateFile($tmpPath, (string) $uploadedFile['name'], (int) $uploadedFile['size']);
        $this->validateMeta($meta);

        $mimeType = $this->detectMime($tmpPath);
        $ext      = $this->safeExtension($mimeType);
        $sha256   = hash_file('sha256', $tmpPath);

        if ($sha256 === false) {
            throw new RuntimeException('Could not compute file checksum.');
        }

        $storedPath = $this->generateStoredPath($employeeId, $ext);
        $absPath    = APP_ROOT . '/' . $storedPath;

        $this->ensureDirectory(dirname($absPath));

        if (!move_uploaded_file($tmpPath, $absPath)) {
            throw new RuntimeException('Failed to move uploaded file to secure storage.');
        }

        try {
            $newDocumentId = $this->connection->transaction(function (PDO $pdo) use (
                $documentId, $employeeId, $meta, $uploadedFile,
                $storedPath, $mimeType, $sha256, $actorId, $repo
            ): int {
                // Mark existing as Superseded
                $repo->markSuperseded($documentId);

                // Insert replacement
                return $repo->create([
                    'employee_id'          => $employeeId,
                    'document_type'        => (string) $meta['document_type'],
                    'document_label'       => (string) ($meta['document_label'] ?? ''),
                    'original_filename'    => (string) $uploadedFile['name'],
                    'stored_path'          => $storedPath,
                    'mime_type'            => $mimeType,
                    'byte_size'            => (int) $uploadedFile['size'],
                    'sha256'               => $sha256,
                    'notes'                => (string) ($meta['notes'] ?? ''),
                    'status'               => 'Current',
                    'replaces_document_id' => $documentId,
                    'uploaded_by'          => $actorId,
                ]);
            });

            $this->writeDocumentAudit(
                $actorId, 'replace_document', $newDocumentId, $employeeId,
                'Replaced document ' . $documentId . ' with new document ' . $newDocumentId
            );

            return $newDocumentId;
        } catch (\Throwable $e) {
            @unlink($absPath);
            throw $e;
        }
    }

    /**
     * Mark a document as verified.
     *
     * @throws RuntimeException
     */
    public function verify(int $documentId, int $employeeId, int $actorId): void
    {
        $repo = new EmployeeDocumentRepository($this->connection);
        $doc  = $repo->findById($documentId);

        if ($doc === null || (int) $doc['employee_id'] !== $employeeId) {
            throw new RuntimeException('Document not found.');
        }
        if ((string) $doc['status'] !== 'Current') {
            throw new RuntimeException('Only a Current document can be verified.');
        }
        if ($doc['verified_at'] !== null) {
            throw new RuntimeException('Document is already verified.');
        }

        $this->connection->transaction(function () use ($repo, $documentId, $actorId, $employeeId): void {
            $repo->markVerified($documentId, $actorId);
            $this->writeDocumentAudit(
                $actorId, 'verify_document', $documentId, $employeeId,
                'Document verified'
            );
        });
    }

    /**
     * Soft-archive a document (retains file and metadata).
     *
     * @throws RuntimeException
     */
    public function archive(int $documentId, int $employeeId, int $actorId): void
    {
        $repo = new EmployeeDocumentRepository($this->connection);
        $doc  = $repo->findById($documentId);

        if ($doc === null || (int) $doc['employee_id'] !== $employeeId) {
            throw new RuntimeException('Document not found.');
        }
        if ((string) $doc['status'] === 'Archived') {
            throw new RuntimeException('Document is already archived.');
        }

        $this->connection->transaction(function () use ($repo, $documentId, $actorId, $employeeId): void {
            $repo->archiveDocument($documentId);
            $this->writeDocumentAudit(
                $actorId, 'archive_document', $documentId, $employeeId,
                'Document archived'
            );
        });
    }

    /**
     * Return all documents for an employee (delegates to repository).
     *
     * @return list<array<string, mixed>>
     */
    public function listForEmployee(int $employeeId): array
    {
        return (new EmployeeDocumentRepository($this->connection))->findByEmployee($employeeId);
    }

    /**
     * Return a single document row, asserting it belongs to the given employee.
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public function getDocument(int $documentId, int $employeeId): array
    {
        $doc = (new EmployeeDocumentRepository($this->connection))->findById($documentId);

        if ($doc === null || (int) $doc['employee_id'] !== $employeeId) {
            throw new RuntimeException('Document not found.');
        }

        return $doc;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /** @param array<string, mixed> $file */
    private function guardUploadError(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('No file was uploaded.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server size limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form size limit.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'No temporary upload directory is configured.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write the upload to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            ];
            throw new RuntimeException($messages[$error] ?? 'Upload failed (error ' . $error . ').');
        }

        if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Invalid upload: not a real uploaded file.');
        }
    }

    private function validateFile(string $tmpPath, string $originalName, int $size): void
    {
        if ($size > self::MAX_BYTES) {
            throw new RuntimeException(
                'File exceeds the 5 MB size limit (' . number_format($size / 1048576, 1) . ' MB uploaded).'
            );
        }

        // Extension check (advisory layer)
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            throw new RuntimeException('Only PDF, JPEG, and PNG files are allowed.');
        }

        // Content-signature (magic bytes) check — primary control per ADR-0003
        $handle = @fopen($tmpPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Cannot read uploaded file for validation.');
        }
        $header = (string) fread($handle, 8);
        fclose($handle);

        foreach (self::ALLOWED_TYPES as $signatures) {
            foreach ($signatures as $sig) {
                if (str_starts_with($header, $sig)) {
                    return; // Signature matched — file is acceptable
                }
            }
        }

        throw new RuntimeException('File content does not match an allowed type (PDF, JPEG, or PNG).');
    }

    /** @param array<string, mixed> $meta */
    private function validateMeta(array $meta): void
    {
        if (!array_key_exists((string) ($meta['document_type'] ?? ''), self::DOCUMENT_TYPES)) {
            throw new RuntimeException('Invalid document type.');
        }
        if (mb_strlen((string) ($meta['notes'] ?? '')) > 1000) {
            throw new RuntimeException('Notes must be 1,000 characters or fewer.');
        }
    }

    private function detectMime(string $tmpPath): string
    {
        $handle = @fopen($tmpPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Cannot open uploaded file.');
        }
        $header = (string) fread($handle, 8);
        fclose($handle);

        foreach (self::ALLOWED_TYPES as $mime => $signatures) {
            foreach ($signatures as $sig) {
                if (str_starts_with($header, $sig)) {
                    return $mime;
                }
            }
        }

        // Fallback — should not reach here after validateFile()
        throw new RuntimeException('Could not determine file MIME type.');
    }

    private function safeExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            default           => 'bin',
        };
    }

    /**
     * Generate a randomised storage path that is never publicly accessible.
     * Format: storage/private/employee-documents/{employeeId}/{hex32}.{ext}
     */
    private function generateStoredPath(int $employeeId, string $ext): string
    {
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        return 'storage/private/employee-documents/' . $employeeId . '/' . $filename;
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create storage directory: ' . $dir);
        }
    }

    /**
     * Persist a new document row inside a transaction and write an audit entry.
     */
    private function persistDocument(
        int     $employeeId,
        string  $documentType,
        string  $documentLabel,
        string  $originalFilename,
        string  $storedPath,
        string  $mimeType,
        int     $byteSize,
        string  $sha256,
        string  $notes,
        string  $status,
        ?int    $replacesDocumentId,
        int     $uploadedBy,
        string  $auditDescription,
    ): int {
        return $this->connection->transaction(function () use (
            $employeeId, $documentType, $documentLabel, $originalFilename,
            $storedPath, $mimeType, $byteSize, $sha256, $notes, $status,
            $replacesDocumentId, $uploadedBy, $auditDescription
        ): int {
            $repo = new EmployeeDocumentRepository($this->connection);

            $documentId = $repo->create([
                'employee_id'          => $employeeId,
                'document_type'        => $documentType,
                'document_label'       => $documentLabel,
                'original_filename'    => $originalFilename,
                'stored_path'          => $storedPath,
                'mime_type'            => $mimeType,
                'byte_size'            => $byteSize,
                'sha256'               => $sha256,
                'notes'                => $notes,
                'status'               => $status,
                'replaces_document_id' => $replacesDocumentId,
                'uploaded_by'          => $uploadedBy,
            ]);

            $this->writeDocumentAudit(
                $uploadedBy, $auditDescription, $documentId, $employeeId,
                'Uploaded: ' . $originalFilename
            );

            return $documentId;
        });
    }

    private function writeDocumentAudit(
        int    $userId,
        string $eventType,
        int    $documentId,
        int    $employeeId,
        string $description
    ): void {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->connection->pdo()->prepare(
            'INSERT INTO audit_logs
                (user_id, event_type, action_performed, table_affected, record_id,
                 description, action_at, created_at)
             VALUES
                (:user_id, :event_type, :action, \'employee_document\', :record_id,
                 :description, :action_at, :created_at)'
        )->execute([
            ':user_id'     => $userId,
            ':event_type'  => $eventType,
            ':action'      => $eventType,
            ':record_id'   => $documentId,
            ':description' => json_encode([
                'employee_id'  => $employeeId,
                'document_id'  => $documentId,
                'description'  => $description,
            ], JSON_UNESCAPED_SLASHES),
            ':action_at'   => $now,
            ':created_at'  => $now,
        ]);
    }
}
