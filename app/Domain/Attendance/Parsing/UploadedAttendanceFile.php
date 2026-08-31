<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

/**
 * Value supplied by the shared upload boundary. The parser never moves or
 * deletes this path and has no HTTP dependency.
 */
final readonly class UploadedAttendanceFile
{
    public function __construct(
        public string $temporaryPath,
        public string $originalFilename,
        public int $sizeBytes,
        public string $sha256,
    ) {
    }
}
