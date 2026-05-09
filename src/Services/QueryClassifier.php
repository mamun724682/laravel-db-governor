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
     * Generate a short audit name from a full SQL string.
     * e.g. "SELECT * FROM users WHERE id=1" → "Read: users (filtered)"
     */
    public function generateReadName(string $sql): string
    {
        $sql = trim($sql);

        if (preg_match('/\bFROM\s+[`"\[]?(\w+)[`"\]]?/i', $sql, $m)) {
            $table = $m[1];
            $hasWhere = (bool) preg_match('/\bWHERE\b/i', $sql);

            return 'Read: '.$table.($hasWhere ? ' (filtered)' : '');
        }

        $short = mb_substr($sql, 0, 60);

        return mb_strlen($sql) > 60 ? $short.'…' : $short;
    }

    /**
     * Generate a short audit name for a table-browse entry.
     *
     * @param  string  $boundWhere  The full WHERE clause with values already interpolated,
     *                              e.g. "WHERE status = 'active'". Pass an empty string for
     *                              unfiltered browses.
     */
    public function generateBrowseName(string $table, string $boundWhere): string
    {
        if ($boundWhere === '') {
            return "Browse {$table}";
        }

        $condition = preg_replace('/^WHERE\s+/i', '', $boundWhere) ?? $boundWhere;
        $label = "Browse {$table} WHERE {$condition}";

        return mb_strlen($label) > 120 ? mb_substr($label, 0, 117).'…' : $label;
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
