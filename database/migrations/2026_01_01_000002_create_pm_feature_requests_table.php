<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pm_feature_requests', function (Blueprint $table) {
            addMetaData($table);
            $table->foreignId('team_id')->constrained();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->unsignedTinyInteger('type')->default(1);     // FeatureRequestTypeEnum::FEATURE
            $table->string('title');
            $table->text('problem')->nullable();
            $table->text('proposed_solution')->nullable();
            $table->json('app_reference')->nullable();           // { module, route, screen }
            $table->json('acceptance_criteria')->nullable();     // [ { given, when, then } ]
            $table->text('menu_design')->nullable();
            $table->unsignedTinyInteger('priority')->default(2);
            $table->unsignedTinyInteger('status')->default(1);   // FeatureRequestStatusEnum::DRAFT
            // Estimation (filled by hand from the documented Claude Code method)
            $table->decimal('effort_days', 6, 2)->nullable();
            $table->unsignedTinyInteger('complexity')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->text('estimate_notes')->nullable();
            $table->foreignId('promoted_from_suggestion_id')->nullable();
            // GitHub link
            $table->unsignedBigInteger('github_issue_number')->nullable();
            $table->string('github_issue_node_id')->nullable();
            $table->timestamp('github_synced_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pm_feature_requests');
    }
};
