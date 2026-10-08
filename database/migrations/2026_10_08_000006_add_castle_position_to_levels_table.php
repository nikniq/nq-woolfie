<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->unsignedSmallInteger('castle_x')->default(0)->after('portals');
            $table->unsignedSmallInteger('castle_y')->default(0)->after('castle_x');
        });
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn(['castle_x', 'castle_y']);
        });
    }
};
