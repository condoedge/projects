<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every inbound GitHub event looks its record up by node id, then by issue number for records
 * linked before node ids were kept. Neither column was indexed, so each webhook scanned both tables.
 *
 * Plain indexes, not unique: soft-deleted rows keep their link, and a unique index would refuse
 * the migration on any database where a record was deleted and its issue linked again.
 */
return new class extends Migration
{
    public function up()
    {
        foreach (['pm_feature_requests', 'pm_tasks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->index('github_issue_node_id', $tableName.'_gh_node_idx');
                $table->index('github_issue_number', $tableName.'_gh_number_idx');
            });
        }
    }

    public function down()
    {
        foreach (['pm_feature_requests', 'pm_tasks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex($tableName.'_gh_node_idx');
                $table->dropIndex($tableName.'_gh_number_idx');
            });
        }
    }
};
