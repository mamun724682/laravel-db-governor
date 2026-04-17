<?php

namespace Mamun724682\DbGovernor\DTOs;

use Mamun724682\DbGovernor\Enums\RiskLevel;

readonly class RiskReport
{
    public function __construct(
        public RiskLevel $level,
        public array $flags,
        public bool $blocked,
        public ?int $estimatedRows,
    ) {}
}
