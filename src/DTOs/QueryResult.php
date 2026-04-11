<?php

namespace Mamun724682\DbGovernor\DTOs;

readonly class QueryResult
{
    public function __construct(
        public bool $success,
        public array $rows = [],
        public ?int $rowsAffected = null,
        public ?int $executionTimeMs = null,
        public ?string $error = null,
    ) {}
}

