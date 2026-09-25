<?php

namespace Condoedge\Projects\Models;

use Condoedge\Projects\Models\Concerns\HasAcceptanceCriteria;
use Condoedge\Projects\Models\Casts\ListValueCast;
use Condoedge\Projects\Models\Enums\PriorityEnum;
use Condoedge\Projects\Models\Enums\TaskKindEnum;
use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Utils\Models\Files\MorphManyFilesTrait;
use Condoedge\Utils\Models\Model;
use Kompo\Auth\Contracts\Security\ScopedToTeam;
use Kompo\Auth\Facades\UserModel;
use Kompo\Auth\Models\Concerns\Security\BelongsToOneTeam;
use Kompo\Auth\Models\Teams\BelongsToTeamTrait;

class ProjectTask extends Model implements ScopedToTeam
{
    use HasAcceptanceCriteria;

    use BelongsToOneTeam, BelongsToTeamTrait, MorphManyFilesTrait, \Condoedge\Projects\Models\Concerns\SyncsWithGithub;

    public const MORPH_ALIAS = 'pm_task';

    protected $table = 'pm_tasks';
    protected $guarded = [];

    protected $casts = [
        'status' => TaskStatusEnum::class,
        'kind' => TaskKindEnum::class,
        'priority' => ListValueCast::class . ":" . ListValue::PRIORITY,
        'acceptance_criteria' => 'array',
        'start_date' => 'date',
        'due_date' => 'date',
        'github_synced_at' => 'datetime',
    ];

    /** GitHub issue body = description + this deliverable's acceptance criteria. */
    public function githubBody(): string
    {
        $body = (string) $this->description;
        $criteria = collect($this->acceptance_criteria ?: [])->filter();
        if ($criteria->isNotEmpty()) {
            $body .= "\n\n## ".__('projects.acceptance-criteria')."\n"
                . $criteria->map(fn ($c) => '- '.$c)->implode("\n");
        }

        return $body;
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function featureRequest()
    {
        return $this->belongsTo(FeatureRequest::class, 'feature_request_id');
    }

    public function assignee()
    {
        return $this->belongsTo(UserModel::getClass(), 'assignee_user_id');
    }

    /** Tasks this one depends on (predecessors). */
    public function dependencies()
    {
        return $this->belongsToMany(static::class, 'pm_task_dependencies', 'successor_task_id', 'predecessor_task_id')
            ->withPivot('type')->withTimestamps();
    }

    /** Tasks that depend on this one (successors). */
    public function dependents()
    {
        return $this->belongsToMany(static::class, 'pm_task_dependencies', 'predecessor_task_id', 'successor_task_id')
            ->withPivot('type')->withTimestamps();
    }

    public function isLinkedToGithub(): bool
    {
        return !is_null($this->github_issue_number);
    }
}
