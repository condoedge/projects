<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pm_suggestions', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(0)->after('status'); // 0-3 stars
        });
    }

    public function down()
    {
        Schema::table('pm_suggestions', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
