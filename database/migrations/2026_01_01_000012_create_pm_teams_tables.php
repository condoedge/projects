<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Working teams inside a project management team. Named pm_teams, and ProjectTeam in code, to
 * keep it apart from the SISC Team that owns it — that one is the tenant, this one is a group of
 * people. team_id points at the owner, as every other table in this module does.
 *
 * direction_user_id points at whoever leads the team, searched across SISC the way a task's
 * assignee is. role_value holds the integer of a pm_list_values entry, like status and priority
 * elsewhere; that list carries no enum, so it starts empty.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('pm_teams', function (Blueprint $table) {
            addMetaData($table);
            $table->foreignId('team_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('direction_user_id')->nullable()->constrained('users');
        });

        Schema::create('pm_team_members', function (Blueprint $table) {
            addMetaData($table);
            $table->foreignId('pm_team_id')->constrained('pm_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->unsignedSmallInteger('role_value')->nullable();

            // Soft deletes make a plain unique index wrong here — a member removed and added back
            // would collide with the deleted row. Uniqueness is enforced in the form instead.
            $table->index(['pm_team_id', 'user_id'], 'pm_team_members_lookup');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pm_team_members');
        Schema::dropIfExists('pm_teams');
    }
};
