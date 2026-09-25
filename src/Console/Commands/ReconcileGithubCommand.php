<?php

namespace Condoedge\Projects\Console\Commands;

use Condoedge\Projects\Jobs\ApplyGithubIssueEvent;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Projects\Services\Github\GithubApiClient;
use Illuminate\Console\Command;

/**
 * Safety net for missed webhooks: re-pulls the current GitHub state of every linked
 * record and re-applies it (open/closed). Runs hourly (see ProjectsServiceProvider).
 */
class ReconcileGithubCommand extends Command
{
    protected $signature = 'projects:reconcile-github';
    protected $description = 'Reconcile linked feature requests / tasks with their GitHub issue state';

    public function handle(GithubApiClient $github): int
    {
        if (!config('projects.github.token')) {
            $this->warn('GITHUB_TOKEN not configured — skipping.');

            return self::SUCCESS;
        }

        $linked = collect()
            ->concat(FeatureRequest::withoutGlobalScopes()->whereNotNull('github_issue_number')->get())
            ->concat(ProjectTask::withoutGlobalScopes()->whereNotNull('github_issue_number')->get());

        foreach ($linked as $entity) {
            [$owner, $repo] = $entity->githubRepoTarget();
            if (!$owner || !$repo) {
                continue;
            }

            try {
                $issue = $github->getIssue($owner, $repo, (int) $entity->github_issue_number);
                $state = $issue['state'] ?? null; // open | closed
                if ($state) {
                    ApplyGithubIssueEvent::dispatch(
                        (int) $entity->github_issue_number,
                        $state === 'closed' ? 'closed' : 'reopened',
                        ['reconcile' => true]
                    );
                }
            } catch (\Throwable $e) {
                $this->error("Issue #{$entity->github_issue_number}: {$e->getMessage()}");
            }
        }

        $this->info('Reconciled '.$linked->count().' linked record(s).');

        return self::SUCCESS;
    }
}
