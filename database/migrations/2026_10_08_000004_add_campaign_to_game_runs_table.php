<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_runs', function (Blueprint $table) {
            $table->string('campaign', 36)->nullable()->index()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('game_runs', function (Blueprint $table) {
            $table->dropColumn('campaign');
        });
    }
};
