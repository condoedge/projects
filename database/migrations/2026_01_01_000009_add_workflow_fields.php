<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('kind')->default(2)->after('feature_request_id'); // TaskKindEnum::DELIVERABLE
            $table->json('acceptance_criteria')->nullable()->after('description');
        });

        Schema::table('pm_feature_requests', function (Blueprint $table) {
            $table->text('ai_review_notes')->nullable()->after('estimate_notes');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_review_notes');
        });
    }

    public function down()
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropColumn(['kind', 'acceptance_criteria']);
        });
        Schema::table('pm_feature_requests', function (Blueprint $table) {
            $table->dropColumn(['ai_review_notes', 'ai_reviewed_at']);
        });
    }
};
