<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetentionManage extends Command
{
    protected $signature = 'retention:manage';
    protected $description = 'Manage data retention policy for ping results';

    public function handle(): int
    {
        while (true) {
            $this->newLine();
            $this->info('=== Data Retention Management ===');
            $this->showCurrentPolicy();

            $action = $this->choice('Select an action', [
                'status' => 'Show database size and retention status',
                'set' => 'Set retention period',
                'drop' => 'Drop old data now',
                'exit' => 'Exit',
            ], 'status');

            match ($action) {
                'status' => $this->showStatus(),
                'set' => $this->setRetention(),
                'drop' => $this->dropOldData(),
                'exit' => exit(0),
            };
        }
    }

    private function showCurrentPolicy(): void
    {
        $policy = DB::selectOne("
            SELECT config 
            FROM timescaledb_information.jobs 
            WHERE proc_name = 'policy_retention' 
            AND hypertable_name = 'ping_results'
        ");

        if ($policy) {
            $config = json_decode($policy->config, true);
            $interval = $config['drop_after'] ?? 'unknown';
            $this->line("Current retention: <info>{$interval}</info>");
        } else {
            $this->line("Current retention: <comment>No policy set (data kept forever)</comment>");
        }
    }

    private function showStatus(): void
    {
        $this->newLine();
        
        // Database size
        $dbSize = DB::selectOne("SELECT pg_size_pretty(pg_database_size('roundtrip')) as size");
        $this->line("Database size: <info>{$dbSize->size}</info>");

        // Row count estimate (faster than COUNT(*))
        $rowCount = DB::selectOne("
            SELECT SUM(approximate_row_count(format('%I.%I', chunk_schema, chunk_name)::regclass)) as count
            FROM timescaledb_information.chunks
            WHERE hypertable_name = 'ping_results'
        ");
        $count = number_format($rowCount->count ?? 0);
        $this->line("Ping results: <info>~{$count} rows</info>");

        // Date range
        $range = DB::selectOne("SELECT MIN(ts) as min_ts, MAX(ts) as max_ts FROM ping_results");
        if ($range->min_ts) {
            $this->line("Data range: <info>{$range->min_ts}</info> to <info>{$range->max_ts}</info>");
        }

        // Chunk sizes with compression status
        $this->newLine();
        $this->line("Chunks:");
        $chunks = DB::select("
            SELECT 
                c.chunk_name,
                pg_size_pretty(c.total_bytes) as size,
                c.range_start::date as start_date,
                c.range_end::date as end_date,
                CASE WHEN cc.chunk_name IS NOT NULL THEN true ELSE false END as is_compressed
            FROM timescaledb_information.chunks c
            LEFT JOIN timescaledb_information.compressed_chunk_stats cc 
                ON c.chunk_name = cc.chunk_name
            WHERE c.hypertable_name = 'ping_results'
            ORDER BY c.range_start
        ");

        foreach ($chunks as $chunk) {
            $status = $chunk->is_compressed ? '<comment>[compressed]</comment>' : '';
            $this->line("  {$chunk->chunk_name}: {$chunk->size} ({$chunk->start_date} to {$chunk->end_date}) {$status}");
        }

        // Show compression policy
        $this->newLine();
        $compPolicy = DB::selectOne("
            SELECT config 
            FROM timescaledb_information.jobs 
            WHERE proc_name = 'policy_compression' 
            AND hypertable_name = 'ping_results'
        ");
        if ($compPolicy) {
            $config = json_decode($compPolicy->config, true);
            $interval = $config['compress_after'] ?? 'unknown';
            $this->line("Compression policy: <info>compress after {$interval}</info>");
        } else {
            $this->line("Compression policy: <comment>not set</comment>");
        }

        // Show continuous aggregates
        $this->newLine();
        $this->line("Continuous aggregates:");
        $aggs = DB::select("
            SELECT 
                view_name,
                pg_size_pretty(pg_total_relation_size(format('%I.%I', view_schema, view_name)::regclass)) as size
            FROM timescaledb_information.continuous_aggregates
            WHERE hypertable_name = 'ping_results'
        ");
        foreach ($aggs as $agg) {
            $this->line("  {$agg->view_name}: {$agg->size}");
        }
        if (empty($aggs)) {
            $this->line("  <comment>none</comment>");
        }
    }

    private function setRetention(): void
    {
        $days = $this->ask('Retention period in days', 90);
        
        if (!is_numeric($days) || $days < 1) {
            $this->error('Invalid number of days');
            return;
        }

        // Remove existing policy first
        DB::statement("SELECT remove_retention_policy('ping_results', if_exists => true)");
        
        // Add new policy
        DB::statement("SELECT add_retention_policy('ping_results', INTERVAL '{$days} days')");
        
        $this->info("Retention policy set to {$days} days.");
        $this->line("Old chunks will be automatically dropped by TimescaleDB's background worker.");
    }

    private function dropOldData(): void
    {
        $days = $this->ask('Drop data older than how many days?', 90);
        
        if (!is_numeric($days) || $days < 1) {
            $this->error('Invalid number of days');
            return;
        }

        if (!$this->confirm("This will permanently delete ping data older than {$days} days. Continue?", false)) {
            $this->info('Cancelled.');
            return;
        }

        $this->line('Dropping old chunks...');
        $result = DB::selectOne("SELECT drop_chunks('ping_results', INTERVAL '{$days} days') as dropped");
        
        $this->info('Done. Dropped chunks: ' . ($result->dropped ?? 'none'));
        
        // Show new size
        $dbSize = DB::selectOne("SELECT pg_size_pretty(pg_database_size('roundtrip')) as size");
        $this->line("Database size now: {$dbSize->size}");
    }
}
