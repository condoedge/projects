<?php

namespace Condoedge\Projects\Models;

use Condoedge\Projects\Models\Concerns\HasTaskProgress;

use Condoedge\Projects\Models\Concerns\HasAcceptanceCriteria;
use Condoedge\Projects\Models\Casts\ListValueCast;
use Condoedge\Projects\Models\Enums\ComplexityEnum;
use Condoedge\Projects\Models\Enums\ConfidenceEnum;
use Condoedge\Projects\Models\Enums\FeatureRequestStatusEnum;
use Condoedge\Projects\Models\Enums\FeatureRequestTypeEnum;
use Condoedge\Projects\Models\Enums\PriorityEnum;
use Condoedge\Utils\Models\Files\MorphManyFilesTrait;
use Condoedge\Utils\Models\Model;
use Kompo\Auth\Contracts\Security\ScopedToTeam;
use Kompo\Auth\Models\Concerns\Security\BelongsToOneTeam;
use Kompo\Auth\Models\Teams\BelongsToTeamTrait;

class FeatureRequest extends Model implements ScopedToTeam
{
    use HasTaskProgress;

    use HasAcceptanceCriteria;

    use BelongsToOneTeam, BelongsToTeamTrait, MorphManyFilesTrait, \Condoedge\Projects\Models\Concerns\SyncsWithGithub;

    public const MORPH_ALIAS = 'pm_feature_request';

    protected $table = 'pm_feature_requests';
    protected $guarded = [];

    protected $casts = [
        'type' => ListValueCast::class . ":" . ListValue::FEATURE_REQUEST_TYPE,
        'status' => ListValueCast::class . ":" . ListValue::FEATURE_REQUEST_STATUS,
        'priority' => ListValueCast::class . ":" . ListValue::PRIORITY,
        'complexity' => ComplexityEnum::class,
        'confidence' => ConfidenceEnum::class,
        'app_reference' => 'array',
        'acceptance_criteria' => 'array',
        'github_synced_at' => 'datetime',
        'ai_reviewed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class, 'feature_request_id');
    }

    public function suggestion()
    {
        return $this->belongsTo(Suggestion::class, 'promoted_from_suggestion_id');
    }

    public function isLinkedToGithub(): bool
    {
        return !is_null($this->github_issue_number);
    }

    /** Full documentation dumped as markdown — fed to Claude Code for estimation and to GitHub as the issue body. */
    public function toMarkdown(): string
    {
        $ref = $this->app_reference ?: [];
        $criteria = $this->criteriaAsMarkdown();

        return implode("\n", array_filter([
            '# '.$this->title,
            '',
            '## '.__('projects.problem'),
            (string) $this->problem,
            '',
            '## '.__('projects.proposed-solution'),
            (string) $this->proposed_solution,
            '',
            '## '.__('projects.app-reference'),
            'Module: '.($ref['module'] ?? '—').' · Route: '.($ref['route'] ?? '—').' · Screen: '.($ref['screen'] ?? '—'),
            '',
            '## '.__('projects.acceptance-criteria'),
            $criteria ?: '—',
            '',
            '## '.__('projects.menu-design'),
            (string) ($this->menu_design ?: '—'),
        ]));
    }
}
