<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_results', function (Blueprint $table) {
            $table->date('daily')->nullable()->index()->after('difficulty');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_results', function (Blueprint $table) {
            $table->dropColumn('daily');
        });
    }
};
