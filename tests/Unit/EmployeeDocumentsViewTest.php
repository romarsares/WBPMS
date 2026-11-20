<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EmployeeDocumentsViewTest extends TestCase
{
    public function testDocumentListHasNoHiddenVerificationColumn(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/hr/employees/documents.php');

        self::assertIsString($view);
        self::assertStringNotContainsString('>Verification<', $view);
        self::assertStringNotContainsString('.document-table th:nth-child(5)', $view);
        self::assertStringNotContainsString('.document-table td:nth-child(5)', $view);
        self::assertStringNotContainsString('$verifiedBy', $view);
        self::assertStringContainsString('<th>Status</th>', $view);
        self::assertStringContainsString('<th style="width:1%">Actions</th>', $view);
    }
}
