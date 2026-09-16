<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_logs', function (Blueprint $table) {
            // 'first' => ran deploy.sh  (new site)
            // 'update' => ran up.sh   (existing site)
            $table->string('kind')->default('update')->after('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_logs', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
