<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable dropdowns — types, statuses, priorities — held in one table keyed by list_key
 * rather than a table per list, so a new list costs a key and no migration.
 *
 * No data migration comes with this: `value` carries the same integers the enums already store
 * in pm_feature_requests.status and friends, so existing rows stay valid untouched. Defaults are
 * materialised from those enums the first time a team opens the settings — a team that has never
 * configured anything reads exactly as it did before.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('pm_list_values', function (Blueprint $table) {
            addMetaData($table);
            $table->foreignId('team_id')->constrained();
            $table->string('list_key', 64);
            $table->unsignedSmallInteger('value');

            // Both null until someone overrides them. A seeded entry keeps reading its label and
            // colour from the enum, so translations stay live instead of being frozen into the
            // row in whatever locale happened to be active when it was created.
            $table->string('name')->nullable();
            $table->string('color', 32)->nullable();

            // Non-null marks an entry the code reaches for by name (GitHub sync maps to DONE,
            // imports write NEW / IN_ANALYSIS, the tasks table toggles COMPLETED). Those refuse
            // deletion; their label and colour stay freely editable.
            $table->string('system_key', 64)->nullable();

            // Drives the lifecycle stepper: "advance" moves to the next position, which is what
            // FeatureRequestsTable and FeatureWorkspacePage each hardcoded as an array.
            $table->unsignedSmallInteger('position')->default(0);

            $table->unique(['team_id', 'list_key', 'value'], 'pm_list_values_unique');
            $table->index(['team_id', 'list_key', 'position'], 'pm_list_values_ordered');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pm_list_values');
    }
};
