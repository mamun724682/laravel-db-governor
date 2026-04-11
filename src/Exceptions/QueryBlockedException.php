<?php

namespace Mamun724682\DbGovernor\Exceptions;

class QueryBlockedException extends \RuntimeException
{
    public function __construct(public readonly array $flags)
    {
        parent::__construct('Query blocked: '.implode('; ', $flags));
    }
}

