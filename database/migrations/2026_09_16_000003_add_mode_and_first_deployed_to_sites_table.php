<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // 'new' = brand new site (first deploy still required)
            // 'update' = site already exists on disk (use up.sh)
            $table->string('mode')->default('new')->after('domain');

            // becomes true the first time a first-deploy succeeds
            $table->boolean('first_deployed')->default(false)->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['mode', 'first_deployed']);
        });
    }
};
