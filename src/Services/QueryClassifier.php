<?php

namespace Mamun724682\DbGovernor\Services;

use Mamun724682\DbGovernor\Enums\QueryType;

class QueryClassifier
{
    /** @var array<int, string> */
    private const READ_VERBS = ['SELECT', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'DESC', 'WITH'];

    /** @var array<int, string> */
    private const WRITE_VERBS = ['INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'MERGE', 'UPSERT'];

    /** @var array<int, string> */
    private const DDL_VERBS = ['CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'RENAME', 'INDEX'];

    public function classify(string $sql): QueryType
    {
        $verb = strtoupper($this->extractVerb($sql));

        if (in_array($verb, self::READ_VERBS, strict: true)) {
            return QueryType::Read;
        }

        if (in_array($verb, self::WRITE_VERBS, strict: true)) {
            return QueryType::Write;
        }

        if (in_array($verb, self::DDL_VERBS, strict: true)) {
            return QueryType::Ddl;
        }

        return QueryType::Unknown;
    }

    public function extractVerb(string $sql): string
    {
        // Strip leading block comments /* ... */
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;

        // Strip leading line comments -- ...
        $sql = preg_replace('#^\s*--[^\n]*\n#m', '', $sql) ?? $sql;

        $tokens = preg_split('/\s+/', ltrim($sql));

        return strtoupper($tokens[0] ?? '');
    }

    /**
     * @return array<int, string>
     */
    public function extractTables(string $sql): array
    {
        // Match table names after FROM, JOIN (all variants), INTO, UPDATE, TABLE keywords
        preg_match_all(
            '/\b(?:FROM|JOIN|INNER\s+JOIN|LEFT\s+JOIN|RIGHT\s+JOIN|FULL\s+JOIN|CROSS\s+JOIN|INTO|UPDATE|TABLE)\s+([`"\[]?[\w.]+[`"\]]?)/i',
            $sql,
            $matches
        );

        $tables = array_map(
            fn ($t) => trim($t, '`"[]'),
            $matches[1] ?? []
        );

        return array_values(array_unique($tables));
    }
}

