<?php

namespace Condoedge\Projects\Kompo\Settings;

use Condoedge\Projects\Models\ProjectTeam;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class ProjectTeamsTable extends WhiteTable
{
    public const ID = 'pm-teams-table';
    public $id = self::ID;

    protected $teamId;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->teamId = (int) $this->prop('team_id');
    }

    public function query()
    {
        return ProjectTeam::asSystemOperation()
            ->where('team_id', $this->teamId)
            ->with(['members', 'direction'])
            ->orderBy('name');
    }

    public function top()
    {
        return _Rows(
            _Button('projects.add-team')->icon('plus')
                ->selfGet('getTeamForm')->inModal(),
        )->class('mb-3');
    }

    public function headers()
    {
        return [
            _Th('projects.team-name'),
            _Th('projects.team-direction')->class('w-40'),
            _Th('projects.team-members')->class('w-28'),
            _Th()->class('w-8'),
        ];
    }

    public function render($projectTeam)
    {
        return _TableRow(
            _Rows(
                _Html($projectTeam->name)->class('font-medium'),
                !$projectTeam->description ? null :
                    _Html($projectTeam->description)->class('text-sm text-gray-500'),
            ),

            _Html($projectTeam->direction?->name ?: '—'),

            _Html($projectTeam->members->count()),

            _TripleDotsDropdown(
                _Link('projects.edit')
                    ->selfGet('getTeamForm', ['id' => $projectTeam->id])->inModal(),
                _DeleteLink('projects.delete')->byKey($projectTeam)->class('text-danger'),
            ),
        );
    }

    public function getTeamForm($id = null)
    {
        return new ProjectTeamForm($id, ['team_id' => $this->teamId]);
    }
}
