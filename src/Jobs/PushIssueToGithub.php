<?php

namespace Condoedge\Projects\Jobs;

use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\GithubSyncLog;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Projects\Services\Github\GithubApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Push a FeatureRequest or ProjectTask to GitHub: create the issue if unlinked,
 * otherwise update its title/body. Stores the issue number/node id back on the record.
 */
class PushIssueToGithub implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public string $entityType, public int $entityId)
    {
    }

    public function handle(GithubApiClient $github): void
    {
        $entity = $this->resolve();
        if (!$entity) {
            return;
        }

        [$owner, $repo] = $entity->githubRepoTarget();
        if (!$owner || !$repo || !config('projects.github.token')) {
            return; // not configured — skip silently
        }

        if ($entity->github_issue_number) {
            $github->updateIssue($owner, $repo, (int) $entity->github_issue_number, [
                'title' => $entity->githubTitle(),
                'body' => $entity->githubBody(),
            ]);
            $entity->markGithubSynced((int) $entity->github_issue_number, $entity->github_issue_node_id);
            GithubSyncLog::record('push', $entity, (int) $entity->github_issue_number, 'updated');

            return;
        }

        $issue = $github->createIssue($owner, $repo, $entity->githubTitle(), $entity->githubBody());
        $entity->markGithubSynced($issue['number'] ?? null, $issue['node_id'] ?? null);
        GithubSyncLog::record('push', $entity, $issue['number'] ?? null, 'created', ['html_url' => $issue['html_url'] ?? null]);
    }

    protected function resolve()
    {
        return match ($this->entityType) {
            'feature_request' => FeatureRequest::find($this->entityId),
            'task' => ProjectTask::find($this->entityId),
            default => null,
        };
    }
}
