<?php

namespace Mamun724682\DbGovernor\DTOs;

readonly class PendingQuery
{
    public function __construct(
        public string $sql,
        public string $connection,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $riskNote = null,
    ) {}
}

