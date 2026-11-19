<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signup_source', 100)->nullable()->index()->after('is_admin');
            $table->string('signup_medium', 100)->nullable()->after('signup_source');
            $table->string('signup_campaign', 100)->nullable()->after('signup_medium');
            $table->string('signup_landing', 255)->nullable()->after('signup_campaign');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['signup_source']);
            $table->dropColumn(['signup_source', 'signup_medium', 'signup_campaign', 'signup_landing']);
        });
    }
};
