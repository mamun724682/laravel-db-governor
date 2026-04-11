<?php

use Illuminate\Support\Facades\Schema;

it('creates the dbg_queries table with all required columns', function () {
    $columns = Schema::getColumnListing(config('db-governor.table_name'));

    expect($columns)->toContain('id')
        ->toContain('connection')
        ->toContain('sql_raw')
        ->toContain('query_type')
        ->toContain('name')
        ->toContain('description')
        ->toContain('risk_note')
        ->toContain('risk_level')
        ->toContain('risk_flags')
        ->toContain('estimated_rows')
        ->toContain('submitted_by')
        ->toContain('submitted_ip')
        ->toContain('status')
        ->toContain('reviewed_by')
        ->toContain('reviewed_at')
        ->toContain('review_note')
        ->toContain('executed_by')
        ->toContain('executed_at')
        ->toContain('rows_affected')
        ->toContain('execution_time_ms')
        ->toContain('execution_error')
        ->toContain('snapshot_strategy')
        ->toContain('snapshot_data')
        ->toContain('snapshot_table')
        ->toContain('snapshot_primary_key')
        ->toContain('snapshot_size_bytes')
        ->toContain('rollback_sql')
        ->toContain('rolled_back_by')
        ->toContain('rolled_back_at')
        ->toContain('rollback_error')
        ->toContain('created_at')
        ->toContain('updated_at');
});

it('uses configurable table name', function () {
    config(['db-governor.table_name' => 'custom_queries']);
    expect(config('db-governor.table_name'))->toBe('custom_queries');
});

