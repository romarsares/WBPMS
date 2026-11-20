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

    public function testSupersededDocumentsAreCollapsedToAvoidDuplicateDisplay(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/hr/employees/documents.php');

        self::assertIsString($view);
        self::assertStringContainsString('$isReplacementHistory = $section === \'Superseded\';', $view);
        self::assertStringContainsString('<details class="replacement-history"', $view);
        self::assertStringContainsString('Replacement History (<?= count($grouped[$section]) ?>)', $view);
        self::assertStringContainsString('hidden by default to avoid duplicate documents.', $view);
    }

    public function testDocumentSearchFiltersEveryStatusAndRevealsReplacementMatches(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/hr/employees/documents.php');

        self::assertIsString($view);
        self::assertStringContainsString('data-document-section', $view);
        self::assertStringContainsString("section.querySelectorAll('[data-document-row]')", $view);
        self::assertStringContainsString("section.classList.contains('replacement-history')", $view);
        self::assertStringContainsString("section.open = query !== '' && matchesInSection > 0;", $view);
        self::assertStringContainsString('id="document-search-empty"', $view);
    }
}
