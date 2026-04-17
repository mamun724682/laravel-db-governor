<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('db-governor.table_name', 'dbg_queries');

        Schema::create($table, function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Query identity
            $table->string('connection');
            $table->string('query_table')->nullable();
            $table->longText('sql_raw');
            $table->string('query_type');
            $table->string('name')->nullable();
            $table->text('description')->nullable();

            // Risk analysis
            $table->string('risk_note')->nullable();
            $table->string('risk_level')->default('low');
            $table->json('risk_flags')->nullable();
            $table->unsignedBigInteger('estimated_rows')->nullable();

            // Submission
            $table->string('submitted_by');
            $table->string('submitted_ip')->nullable();

            // Approval workflow
            $table->string('status')->default('pending');
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Execution
            $table->string('executed_by')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->unsignedBigInteger('rows_affected')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->text('execution_error')->nullable();

            // Snapshot / rollback
            $table->string('snapshot_strategy')->nullable();
            $table->longText('snapshot_data')->nullable();
            $table->string('snapshot_primary_key')->nullable();
            $table->unsignedBigInteger('snapshot_size_bytes')->nullable();
            $table->longText('rollback_sql')->nullable();
            $table->string('rolled_back_by')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->text('rollback_error')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('submitted_by');
            $table->index('connection');
            $table->index('query_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        $table = config('db-governor.table_name', 'dbg_queries');
        Schema::dropIfExists($table);
    }
};

