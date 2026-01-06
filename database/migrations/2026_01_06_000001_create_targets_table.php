<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedInteger('interval_seconds')->default(5);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
