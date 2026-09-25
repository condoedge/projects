<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pm_suggestions', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('body');
            $table->foreignId('requested_by_user_id')->nullable()->after('submitted_by')->constrained('users');
            $table->string('requester_name')->nullable()->after('requested_by_user_id');
            $table->string('reference')->nullable()->index()->after('requester_name');
        });
    }

    public function down()
    {
        Schema::table('pm_suggestions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by_user_id');
            $table->dropColumn(['internal_notes', 'requester_name', 'reference']);
        });
    }
};
