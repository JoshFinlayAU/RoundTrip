<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('id');
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });

        Schema::dropIfExists('groups');
    }
};
