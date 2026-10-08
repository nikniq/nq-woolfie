<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->string('player_name', 24)->default('Anonymous');
            $table->string('outcome', 16); // completed | caught
            $table->unsignedInteger('time_ms');
            $table->timestamps();

            $table->index(['level_id', 'outcome', 'time_ms']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_runs');
    }
};
