<?php

namespace Mamun724682\DbGovernor\Exceptions;

class RollbackFailedException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
