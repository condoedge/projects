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

        // The project comes eagerly and unscoped: one query instead of one per record, and the
        // console has no team in context for the scoped relation to resolve against.
        $withProject = ['project' => fn ($q) => $q->withoutGlobalScopes()];

        $linked = collect()
            ->concat(FeatureRequest::withoutGlobalScopes()->with($withProject)->whereNotNull('github_issue_number')->get())
            ->concat(ProjectTask::withoutGlobalScopes()->with($withProject)->whereNotNull('github_issue_number')->get());

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
                        ['reconcile' => true],
                        $issue['node_id'] ?? $entity->github_issue_node_id,
                        "{$owner}/{$repo}"
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
