<?php

namespace Mamun724682\DbGovernor\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GovernedQuery extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'connection',
        'created_at',
        'updated_at',
        'sql_raw',
        'query_type',
        'name',
        'description',
        'risk_note',
        'risk_level',
        'risk_flags',
        'estimated_rows',
        'submitted_by',
        'submitted_ip',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'executed_by',
        'executed_at',
        'rows_affected',
        'execution_time_ms',
        'execution_error',
        'snapshot_strategy',
        'snapshot_data',
        'snapshot_table',
        'snapshot_primary_key',
        'snapshot_size_bytes',
        'rollback_sql',
        'rolled_back_by',
        'rolled_back_at',
        'rollback_error',
    ];

    protected $casts = [
        'risk_flags'     => 'array',
        'reviewed_at'    => 'datetime',
        'executed_at'    => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('db-governor.table_name', 'dbg_queries');
    }

    public function getConnectionName(): string
    {
        return config('db-governor.governance_connection') ?? config('database.default');
    }
}


