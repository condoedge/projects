<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pm_suggestions', function (Blueprint $table) {
            addMetaData($table);
            $table->foreignId('team_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained('pm_projects')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->unsignedTinyInteger('status')->default(1);   // SuggestionStatusEnum::NEW
            $table->unsignedInteger('votes')->default(0);
            // Unconstrained (avoids circular FK with pm_feature_requests.promoted_from_suggestion_id)
            $table->foreignId('promoted_to_feature_request_id')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pm_suggestions');
    }
};
