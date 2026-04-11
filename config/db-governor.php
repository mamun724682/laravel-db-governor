<?php

return [
    // URL prefix for the package.
    'path' => env('DB_GOVERNOR_PATH', 'db-governor'),

    // Table name used to store governance queries.
    'table_name' => env('DB_GOVERNOR_TABLE', 'dbg_queries'),

    // Middleware applied to ALL package routes (except login).
    'middleware' => ['web'],

    // How long a login token stays valid (in hours).
    'token_expiry_hours' => env('DB_GOVERNOR_TOKEN_EXPIRY', 8),

    // admins   — can approve/reject, trigger execution, trigger rollback, see all queries
    // employees — can submit queries (SELECT runs immediately; WRITE goes to queue)
    'allowed' => [
        'admins'    => array_filter(explode(',', env('DB_GOVERNOR_ADMINS', ''))),
        'employees' => array_filter(explode(',', env('DB_GOVERNOR_EMPLOYEES', ''))),
    ],

    // Named map: connection key (URL slug) → Laravel DB connection name
    // Single:   ['prod' => env('DB_CONNECTION', 'mysql')]
    // Multiple: ['main' => 'mysql', 'replica' => 'mysql_read', 'legacy' => 'pgsql']
    'connections' => [
        env('DB_GOVERNOR_CONNECTION_KEY', 'main') => env('DB_GOVERNOR_CONNECTION', 'mysql'),
    ],

    // Regex patterns that BLOCK a query outright (status set to 'blocked').
    'blocked_patterns' => [
        '/^\s*DROP\s+(TABLE|DATABASE|SCHEMA)/i',
        '/^\s*TRUNCATE\s+/i',
    ],

    // Regex patterns that FLAG a query as high risk (queued but prominently warned).
    'flagged_patterns' => [
        '/UPDATE\s+\w[\w.]*\s+SET(?!.*\bWHERE\b)/is',
        '/DELETE\s+FROM\s+\w[\w.]*\s*(?!WHERE)/is',
    ],

    'max_affected_rows' => env('DB_GOVERNOR_MAX_ROWS', 1000),
    'dry_run_enabled'   => env('DB_GOVERNOR_DRY_RUN', true),

    // 'row_snapshot' | 'generated_sql' | 'none'
    'rollback_strategy' => env('DB_GOVERNOR_ROLLBACK', 'row_snapshot'),
    'snapshot_max_rows' => env('DB_GOVERNOR_SNAPSHOT_MAX', 500),

    // Separate connection for the governance table itself (null = app default).
    'governance_connection' => env('DB_GOVERNOR_STORAGE_CONNECTION', null),

    // Tables to hide from the sidebar and dashboard table list.
    // Add any internal/framework tables you don't want exposed in the UI.
    'hidden_tables' => [
        'dbg_queries',
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'pulse_aggregates',
        'pulse_entries',
        'pulse_values',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ],
];

