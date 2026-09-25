<?php

namespace Condoedge\Projects\Jobs;

use Condoedge\Projects\Models\Enums\FeatureRequestStatusEnum;
use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\GithubSyncLog;
use Condoedge\Projects\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Apply an inbound GitHub issue event (webhook or reconcile) to the linked record.
 * closed → done/completed, reopened/opened → in progress. Idempotent per (issue, action).
 */
class ApplyGithubIssueEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /** @param string $action  github action: closed | reopened | opened | edited | labeled */
    public function __construct(public int $issueNumber, public string $action, public array $payload = [])
    {
    }

    public function handle(): void
    {
        $fr = FeatureRequest::withoutGlobalScopes()->where('github_issue_number', $this->issueNumber)->first();
        $task = ProjectTask::withoutGlobalScopes()->where('github_issue_number', $this->issueNumber)->first();

        if ($fr) {
            $this->applyToFeatureRequest($fr);
        }
        if ($task) {
            $this->applyToTask($task);
        }

        if ($fr || $task) {
            GithubSyncLog::record('pull', $fr ?: $task, $this->issueNumber, $this->action, $this->payload);
        }
    }

    protected function applyToFeatureRequest(FeatureRequest $fr): void
    {
        $new = match ($this->action) {
            'closed' => FeatureRequestStatusEnum::DONE,
            'reopened', 'opened' => FeatureRequestStatusEnum::IN_PROGRESS,
            default => null,
        };
        if ($new) {
            $fr->status = $new;
            $fr->github_synced_at = now();
            $fr->saveQuietly();
        }
    }

    protected function applyToTask(ProjectTask $task): void
    {
        $new = match ($this->action) {
            'closed' => TaskStatusEnum::COMPLETED,
            'reopened', 'opened' => TaskStatusEnum::IN_PROGRESS,
            default => null,
        };
        if ($new) {
            $task->status = $new;
            if ($new === TaskStatusEnum::COMPLETED) {
                $task->completion_pct = 100;
            }
            $task->github_synced_at = now();
            $task->saveQuietly();
        }
    }
}
