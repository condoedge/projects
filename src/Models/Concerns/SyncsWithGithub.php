<?php

namespace Condoedge\Projects\Models\Concerns;

/**
 * Shared GitHub-issue linkage for FeatureRequest and ProjectTask.
 * Both carry github_issue_number / github_issue_node_id / github_synced_at columns.
 */
trait SyncsWithGithub
{
    public function githubTitle(): string
    {
        return (string) $this->title;
    }

    public function githubBody(): string
    {
        // FeatureRequest ships a rich markdown dump; tasks fall back to their description.
        return method_exists($this, 'toMarkdown') ? $this->toMarkdown() : (string) $this->description;
    }

    /** [owner, repo] for this record, resolved through its project (with config fallback). */
    public function githubRepoTarget(): array
    {
        $project = $this->project;

        return [
            $project?->githubOwner() ?: config('projects.github.owner'),
            $project?->githubRepo() ?: config('projects.github.repo'),
        ];
    }

    public function markGithubSynced(?int $number, ?string $nodeId): void
    {
        $this->github_issue_number = $number;
        $this->github_issue_node_id = $nodeId;
        $this->github_synced_at = now();
        $this->saveQuietly();
    }
}
