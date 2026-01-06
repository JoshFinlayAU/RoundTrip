<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ping_results', function (Blueprint $table) {
            $table->unsignedBigInteger('target_id');
            $table->timestampTz('ts');
            $table->double('min_ms')->nullable();
            $table->double('avg_ms')->nullable();
            $table->double('max_ms')->nullable();
            $table->double('loss_pct')->nullable();

            $table->foreign('target_id')->references('id')->on('targets')->cascadeOnDelete();
            $table->index(['target_id', 'ts']);
        });

        DB::statement("SELECT create_hypertable('ping_results', by_range('ts'));");
    }

    public function down(): void
    {
        Schema::dropIfExists('ping_results');
    }
};
