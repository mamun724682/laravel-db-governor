<?php

namespace Mamun724682\DbGovernor\DTOs;

readonly class SnapshotData
{
    public function __construct(
        public string $strategy,
        public string $tableName,
        public array $rows,
        public string $primaryKey,
    ) {}
}

