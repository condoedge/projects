<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pm_projects', function (Blueprint $table) {
            addMetaData($table);
            $table->foreignId('team_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(1);   // ProjectStatusEnum::ACTIVE
            $table->unsignedTinyInteger('priority')->default(2);  // PriorityEnum::MEDIUM
            $table->foreignId('owner_person_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('github_owner')->nullable();
            $table->string('github_repo')->nullable();
            $table->json('settings')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pm_projects');
    }
};
