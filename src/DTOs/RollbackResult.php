<?php

namespace Mamun724682\DbGovernor\DTOs;

readonly class RollbackResult
{
    public function __construct(
        public bool $success,
        public ?int $rowsRestored = null,
        public ?string $message = null,
    ) {}
}
