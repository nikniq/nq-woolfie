<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('bodies_hidden')->default(0)->after('stones');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_results', function (Blueprint $table) {
            $table->dropColumn('bodies_hidden');
        });
    }
};
