<?php

namespace Condoedge\Projects\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Loads the projects-module fixture (pm_seed.json) into the pm_* tables so a fresh
 * checkout ends up with the exact same data the module was built against.
 *
 * Idempotent: replaces the pm_* rows wholesale. Refuses to run if the tables already
 * hold data unless --force is passed (guard against clobbering work in progress).
 */
class SeedPmDataCommand extends Command
{
    protected $signature = 'projects:seed-data
        {--force : Overwrite even if the pm_ tables already contain rows}';

    protected $description = 'Populate the pm_* tables from the committed fixture (pm_seed.json)';

    /** Insert order respects the FKs; truncate walks it in reverse. */
    protected array $tables = [
        'pm_projects',
        'pm_suggestions',
        'pm_feature_requests',
        'pm_tasks',
        'pm_task_dependencies',
        'pm_github_sync_logs',
    ];

    public function handle(): int
    {
        $file = __DIR__.'/../../../database/data/pm_seed.json';
        if (!File::exists($file)) {
            $this->error("Fixture not found: $file");

            return self::FAILURE;
        }

        $data = json_decode(File::get($file), true);
        if (!is_array($data)) {
            $this->error('Fixture is not valid JSON.');

            return self::FAILURE;
        }

        $existing = collect($this->tables)
            ->filter(fn ($t) => Schema::hasTable($t) && DB::table($t)->exists());

        if ($existing->isNotEmpty() && !$this->option('force')) {
            $this->error('These pm_ tables already hold data: '.$existing->implode(', '));
            $this->line('Re-run with --force to overwrite (this replaces all pm_* rows).');

            return self::FAILURE;
        }

        DB::transaction(function () use ($data) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach (array_reverse($this->tables) as $t) {
                DB::table($t)->delete(); // not truncate: TRUNCATE implicit-commits and breaks the transaction
            }
            foreach ($this->tables as $t) {
                $rows = $data[$t] ?? [];
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($t)->insert($chunk);
                }
                $this->line(str_pad($t, 26).count($rows));
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        });

        $this->info('pm_* tables seeded from fixture.');

        return self::SUCCESS;
    }
}
