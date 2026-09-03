<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbpms\Application\AuthenticationPolicy;

final class AuthenticationPolicyTest extends TestCase
{
    public function testEmployeeWithoutLinkIsRejected(): void
    {
        self::assertTrue(AuthenticationPolicy::isUnlinkedEmployee('Employee', null));
    }

    public function testLinkedEmployeeIsAccepted(): void
    {
        self::assertFalse(AuthenticationPolicy::isUnlinkedEmployee('Employee', 42));
    }

    public function testBusinessOwnerDoesNotRequireEmployeeLink(): void
    {
        self::assertFalse(AuthenticationPolicy::isUnlinkedEmployee('BusinessOwner', null));
    }
}