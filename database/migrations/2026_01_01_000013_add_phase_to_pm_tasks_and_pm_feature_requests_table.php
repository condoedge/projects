<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Nullable, no default — unlike priority/status/type, a phase is assigned after the
        // fact rather than chosen at creation, and a team may not have defined any yet.
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('phase')->nullable()->after('priority');
        });

        Schema::table('pm_feature_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('phase')->nullable()->after('priority');
        });
    }

    public function down()
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropColumn('phase');
        });

        Schema::table('pm_feature_requests', function (Blueprint $table) {
            $table->dropColumn('phase');
        });
    }
};
