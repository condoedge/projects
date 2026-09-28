<?php

namespace Condoedge\Projects\Jobs;

use Condoedge\Projects\Models\Enums\FeatureRequestStatusEnum;
use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\GithubSyncLog;
use Condoedge\Projects\Models\Project;
use Condoedge\Projects\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Apply an inbound GitHub issue event (webhook or reconcile) to the linked record.
 *
 * Only the open/closed state crosses over, and only where the two sides disagree: a closed issue
 * closes a record still open here, a reopened issue reopens a record marked done. Every other
 * stage — draft, ready, decomposed, pending, blocked — is a decision GitHub knows nothing about,
 * and an issue that is merely still open must not overwrite it. That also makes the job
 * idempotent: replaying an event, or the hourly reconcile, changes nothing already in step.
 */
class ApplyGithubIssueEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * issue.node_id, unique across GitHub, and "owner/repo".
     *
     * Declared with defaults rather than promoted: a job queued before these existed unserialises
     * with null here instead of failing on an uninitialised typed property.
     */
    public ?string $nodeId = null;
    public ?string $repository = null;

    /** @param string $action  github action: closed | reopened | opened | edited | labeled | commented */
    public function __construct(
        public int $issueNumber,
        public string $action,
        public array $payload = [],
        ?string $nodeId = null,
        ?string $repository = null,
    ) {
        $this->nodeId = $nodeId;
        $this->repository = $repository;
    }

    public function handle(): void
    {
        foreach ([FeatureRequest::class, ProjectTask::class] as $class) {
            $record = $this->findLinked($class);

            if (!$record) {
                continue;
            }

            $changed = $record instanceof FeatureRequest
                ? $this->applyToFeatureRequest($record)
                : $this->applyToTask($record);

            // A webhook is worth a line even when it changes nothing; the hourly reconcile finding
            // everything in step is not, or the log grows by one row per linked record per hour.
            if ($changed || empty($this->payload['reconcile'])) {
                GithubSyncLog::record('pull', $record, $this->issueNumber, $this->action, $this->payload);
            }
        }
    }

    /**
     * The record this issue is linked to.
     *
     * The node id is unique across GitHub, so it decides alone. The number is only unique inside
     * one repository — and each project can point at its own — so a match on it must also agree on
     * the repository. Records linked before node ids were stored are found that way, and get theirs
     * filled in on the way through.
     */
    protected function findLinked(string $class)
    {
        if ($this->nodeId) {
            $record = $class::withoutGlobalScopes()->where('github_issue_node_id', $this->nodeId)->first();

            if ($record) {
                return $record;
            }
        }

        $candidates = $class::withoutGlobalScopes()
            ->where('github_issue_number', $this->issueNumber)
            // A record holding a different node id is linked to a different issue.
            ->when($this->nodeId, fn ($q) => $q->whereNull('github_issue_node_id'))
            ->get()
            ->filter(fn ($record) => $this->inRepository($record))
            ->values();

        // With no repository to check against — a job queued before it was passed — the number is
        // only trusted when it names a single record.
        if (!$this->repository && $candidates->count() > 1) {
            return null;
        }

        $record = $candidates->first();

        if ($record && $this->nodeId) {
            $record->github_issue_node_id = $this->nodeId;
            $record->saveQuietly();
        }

        return $record;
    }

    /**
     * Resolves the project without its team scope: a queue worker has no team in context, and the
     * scoped relation would fall back to the default repository.
     */
    protected function inRepository($record): bool
    {
        if (!$this->repository) {
            return true;
        }

        $project = Project::withoutGlobalScopes()->find($record->project_id);

        [$owner, $repo] = $project
            ? [$project->githubOwner(), $project->githubRepo()]
            : [config('projects.github.owner'), config('projects.github.repo')];

        return $owner && $repo && strcasecmp("{$owner}/{$repo}", $this->repository) === 0;
    }

    protected function closesIssue(): bool
    {
        return $this->action === 'closed';
    }

    protected function opensIssue(): bool
    {
        return in_array($this->action, ['reopened', 'opened'], true);
    }

    /** Rejected counts as closed here too: a closed issue has nothing to add to either. */
    protected function applyToFeatureRequest(FeatureRequest $fr): bool
    {
        $isDone = (bool) $fr->status?->isKey('DONE');
        $isClosed = $isDone || $fr->status?->isKey('REJECTED');

        $new = match (true) {
            $this->closesIssue() && !$isClosed => FeatureRequestStatusEnum::DONE,
            $this->opensIssue() && $isDone => FeatureRequestStatusEnum::IN_PROGRESS,
            default => null,
        };

        if (!$new) {
            return false;
        }

        $fr->status = $new;
        $fr->github_synced_at = now();
        $fr->saveQuietly();

        return true;
    }

    /** Cancelled counts as closed here too, for the same reason. */
    protected function applyToTask(ProjectTask $task): bool
    {
        $isDone = $task->status === TaskStatusEnum::COMPLETED;
        $isClosed = $isDone || $task->status === TaskStatusEnum::CANCELLED;

        $new = match (true) {
            $this->closesIssue() && !$isClosed => TaskStatusEnum::COMPLETED,
            $this->opensIssue() && $isDone => TaskStatusEnum::IN_PROGRESS,
            default => null,
        };

        if (!$new) {
            return false;
        }

        $task->status = $new;
        if ($new === TaskStatusEnum::COMPLETED) {
            $task->completion_pct = 100;
        }
        $task->github_synced_at = now();
        $task->saveQuietly();

        return true;
    }
}
