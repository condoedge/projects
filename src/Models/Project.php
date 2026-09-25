<?php

namespace Condoedge\Projects\Models;

use Condoedge\Projects\Models\Concerns\HasTaskProgress;

use Condoedge\Projects\Models\Casts\ListValueCast;
use Condoedge\Projects\Models\Enums\PriorityEnum;
use Condoedge\Projects\Models\Enums\ProjectStatusEnum;
use Condoedge\Utils\Models\Model;
use Kompo\Auth\Contracts\Security\ScopedToTeam;
use Kompo\Auth\Facades\TeamModel;
use Kompo\Auth\Models\Concerns\Security\BelongsToOneTeam;
use Kompo\Auth\Models\Teams\BelongsToTeamTrait;

class Project extends Model implements ScopedToTeam
{
    use HasTaskProgress;

    use BelongsToOneTeam, BelongsToTeamTrait;

    protected $table = 'pm_projects';
    protected $guarded = [];

    protected $casts = [
        'status' => ProjectStatusEnum::class,
        'priority' => ListValueCast::class . ":" . ListValue::PRIORITY,
        'settings' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function suggestions()
    {
        return $this->hasMany(Suggestion::class, 'project_id');
    }

    public function featureRequests()
    {
        return $this->hasMany(FeatureRequest::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class, 'project_id');
    }

    /**
     * The team() relation is scoped out once a team is closed, so a project attached to one
     * reads as teamless. TeamSettingsPage reaches for the same escape hatch; going through the
     * kompo/auth facade keeps this package off App\.
     */
    public function teamName(): ?string
    {
        return $this->team_id
            ? TeamModel::withClosedAndDeleted()->find($this->team_id)?->team_name
            : null;
    }

    /** Resolve the target GitHub repo for this project (falls back to config). */
    public function githubOwner(): ?string
    {
        return $this->github_owner ?: config('projects.github.owner');
    }

    public function githubRepo(): ?string
    {
        return $this->github_repo ?: config('projects.github.repo');
    }
}
