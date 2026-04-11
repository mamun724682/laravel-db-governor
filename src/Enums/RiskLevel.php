<?php

namespace Mamun724682\DbGovernor\Enums;

enum RiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * @return array<int, self>
     */
    private static function orderedCases(): array
    {
        return [self::Low, self::Medium, self::High, self::Critical];
    }

    private function ordinal(): int
    {
        return array_search($this, self::orderedCases(), true);
    }

    public function escalateTo(self $other): self
    {
        return $this->ordinal() >= $other->ordinal() ? $this : $other;
    }
}

