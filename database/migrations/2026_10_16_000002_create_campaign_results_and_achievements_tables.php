<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_results', function (Blueprint $table) {
            $table->id();
            $table->string('campaign', 36)->unique();
            $table->string('player_name', 24)->default('Anonymous')->index();
            $table->string('difficulty', 8)->default('normal');
            $table->string('outcome', 16); // escaped | failed
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('time_ms')->default(0);
            $table->unsignedSmallInteger('kills')->default(0);
            $table->unsignedSmallInteger('alarms')->default(0);
            $table->unsignedSmallInteger('lives_lost')->default(0);
            $table->unsignedSmallInteger('stones')->default(0);
            $table->unsignedSmallInteger('rooms_cleared')->default(0);
            $table->unsignedSmallInteger('rooms_total')->default(0);
            $table->boolean('all_gold')->default(false);
            $table->boolean('all_prisoners')->default(false);
            $table->boolean('disguised_at_end')->default(false);
            $table->timestamps();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('player_name', 24);
            $table->string('key', 32);
            $table->string('campaign', 36)->nullable();
            $table->timestamp('earned_at');
            $table->unique(['player_name', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('campaign_results');
    }
};
