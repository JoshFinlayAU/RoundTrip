<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 90-day retention policy for ping_results
        // TimescaleDB will automatically drop chunks older than this
        DB::statement("SELECT add_retention_policy('ping_results', INTERVAL '90 days', if_not_exists => true)");
    }

    public function down(): void
    {
        DB::statement("SELECT remove_retention_policy('ping_results', if_exists => true)");
    }
};
