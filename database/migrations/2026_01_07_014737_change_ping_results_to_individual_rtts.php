<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old columns and add new ones for individual RTT storage
        Schema::table('ping_results', function (Blueprint $table) {
            $table->dropColumn(['min_ms', 'avg_ms', 'max_ms', 'loss_pct']);
            $table->float('rtt_ms')->nullable()->after('ts');
            $table->smallInteger('seq')->default(0)->after('rtt_ms');
            $table->boolean('lost')->default(false)->after('seq');
        });

        // Add index for efficient queries
        Schema::table('ping_results', function (Blueprint $table) {
            $table->index(['target_id', 'ts', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::table('ping_results', function (Blueprint $table) {
            $table->dropIndex(['target_id', 'ts', 'seq']);
            $table->dropColumn(['rtt_ms', 'seq', 'lost']);
            $table->float('min_ms')->nullable();
            $table->float('avg_ms')->nullable();
            $table->float('max_ms')->nullable();
            $table->float('loss_pct')->nullable();
        });
    }
};
