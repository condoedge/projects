<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pm_tasks', function (Blueprint $table) {
            addMetaData($table);
            $table->foreignId('team_id')->constrained();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->foreignId('feature_request_id')->nullable()->constrained('pm_feature_requests')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users');
            $table->unsignedTinyInteger('status')->default(1);   // TaskStatusEnum::PENDING
            $table->unsignedTinyInteger('priority')->default(2);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('estimate_days', 6, 2)->nullable();
            $table->unsignedTinyInteger('completion_pct')->default(0);
            $table->integer('order')->nullable();
            $table->unsignedBigInteger('github_issue_number')->nullable();
            $table->string('github_issue_node_id')->nullable();
            $table->timestamp('github_synced_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pm_tasks');
    }
};
