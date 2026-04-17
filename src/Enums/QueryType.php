<?php

namespace Mamun724682\DbGovernor\Enums;

enum QueryType: string
{
    case Read = 'read';
    case Write = 'write';
    case Ddl = 'ddl';
    case Unknown = 'unknown';

    public function isRead(): bool
    {
        return $this === self::Read;
    }
}
