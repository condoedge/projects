<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pm_github_sync_logs', function (Blueprint $table) {
            addMetaData($table);
            $table->string('direction');             // push | pull
            $table->string('entity_type');           // feature_request | task
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('issue_number')->nullable();
            $table->string('action')->nullable();    // created | updated | closed | reopened | commented | labeled
            $table->json('payload')->nullable();
            $table->index(['entity_type', 'entity_id']);
            $table->index('issue_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pm_github_sync_logs');
    }
};
