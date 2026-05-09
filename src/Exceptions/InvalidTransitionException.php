<?php

namespace Mamun724682\DbGovernor\Exceptions;

class InvalidTransitionException extends \RuntimeException
{
    public function __construct(string $currentStatus, string $requiredStatus, string $action)
    {
        parent::__construct(
            "Cannot {$action} a query with status '{$currentStatus}' (requires: '{$requiredStatus}')."
        );
    }
}
