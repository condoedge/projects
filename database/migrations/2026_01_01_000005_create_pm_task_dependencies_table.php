<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pm_task_dependencies', function (Blueprint $table) {
            addMetaData($table);
            $table->foreignId('predecessor_task_id')->constrained('pm_tasks')->cascadeOnDelete();
            $table->foreignId('successor_task_id')->constrained('pm_tasks')->cascadeOnDelete();
            $table->unsignedTinyInteger('type')->default(1);     // DependencyTypeEnum::FS
            $table->unique(['predecessor_task_id', 'successor_task_id'], 'pm_dep_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pm_task_dependencies');
    }
};
