<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable compression on ping_results
        // Compresses data older than 7 days, reduces storage by ~90%
        DB::statement("
            ALTER TABLE ping_results SET (
                timescaledb.compress,
                timescaledb.compress_segmentby = 'target_id',
                timescaledb.compress_orderby = 'ts DESC, seq'
            )
        ");
        
        // Add compression policy - compress chunks older than 7 days
        DB::statement("SELECT add_compression_policy('ping_results', INTERVAL '7 days', if_not_exists => true)");

        // Create continuous aggregate for hourly rollups (for long-term queries)
        // This pre-computes min/avg/max per hour per target
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS ping_results_hourly
            WITH (timescaledb.continuous) AS
            SELECT
                time_bucket('1 hour', ts) AS bucket,
                target_id,
                MIN(rtt_ms) AS min_ms,
                AVG(rtt_ms) AS avg_ms,
                MAX(rtt_ms) AS max_ms,
                COUNT(*) AS sample_count,
                SUM(CASE WHEN lost THEN 1 ELSE 0 END) AS lost_count
            FROM ping_results
            GROUP BY bucket, target_id
            WITH NO DATA
        ");

        // Add refresh policy - refresh hourly data continuously
        DB::statement("
            SELECT add_continuous_aggregate_policy('ping_results_hourly',
                start_offset => INTERVAL '3 hours',
                end_offset => INTERVAL '1 hour',
                schedule_interval => INTERVAL '1 hour',
                if_not_exists => true
            )
        ");

        // Create 5-minute aggregate for medium-term queries
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS ping_results_5min
            WITH (timescaledb.continuous) AS
            SELECT
                time_bucket('5 minutes', ts) AS bucket,
                target_id,
                MIN(rtt_ms) AS min_ms,
                AVG(rtt_ms) AS avg_ms,
                MAX(rtt_ms) AS max_ms,
                COUNT(*) AS sample_count,
                SUM(CASE WHEN lost THEN 1 ELSE 0 END) AS lost_count
            FROM ping_results
            GROUP BY bucket, target_id
            WITH NO DATA
        ");

        // Refresh 5-min aggregate every 5 minutes
        DB::statement("
            SELECT add_continuous_aggregate_policy('ping_results_5min',
                start_offset => INTERVAL '30 minutes',
                end_offset => INTERVAL '5 minutes',
                schedule_interval => INTERVAL '5 minutes',
                if_not_exists => true
            )
        ");
    }

    public function down(): void
    {
        // Remove policies first
        DB::statement("SELECT remove_continuous_aggregate_policy('ping_results_hourly', if_exists => true)");
        DB::statement("SELECT remove_continuous_aggregate_policy('ping_results_5min', if_exists => true)");
        DB::statement("SELECT remove_compression_policy('ping_results', if_exists => true)");
        
        // Drop continuous aggregates
        DB::statement("DROP MATERIALIZED VIEW IF EXISTS ping_results_hourly CASCADE");
        DB::statement("DROP MATERIALIZED VIEW IF EXISTS ping_results_5min CASCADE");
        
        // Disable compression (note: doesn't decompress existing data)
        DB::statement("ALTER TABLE ping_results SET (timescaledb.compress = false)");
    }
};
