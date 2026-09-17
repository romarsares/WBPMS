<?php

declare(strict_types=1);

namespace Wbpms\Application;

/** Raised only after valid credentials identify an unlinked Employee account. */
final class UnlinkedEmployeeAccountException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(AuthenticationPolicy::UNLINKED_EMPLOYEE_MESSAGE);
    }
}