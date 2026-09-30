<?php

namespace Condoedge\Projects\Kompo\Settings;

use Condoedge\Projects\Models\ListValue;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * The configuration that belongs to the whole SISC team, not to any one of its projects: the
 * dropdowns every project draws from, and the working teams assigned to tasks.
 *
 * Used to live inside each project's own Paramètres tab, which meant editing "Priorités" from
 * project A silently reshaped project B's dropdown too — the lists were never actually
 * per-project. It now lives once, on the team's project list (ProjectsPage), where its scope
 * matches what it actually controls: currentTeamId(), not any one project's team_id.
 */
class TeamSettingsTab extends Form
{
    public $containerClass = '';

    protected int $teamId;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->teamId = (int) ($this->prop('team_id') ?: currentTeamId());
    }

    public function render()
    {
        return _Rows(
            // mt-2 clears the tab bar above: the two sat flush against each other otherwise —
            // "Paramètres d'équipe" and "Listes configurables" read as one crowded line.
            _Html('projects.configurable-lists')->class('font-semibold text-lg mt-2 mb-2'),
            _Html('projects.configurable-lists-hint')->class('text-sm text-gray-500 mb-6'),

            _Rows(
                _Columns(
                    $this->listCard('projects.list-feature-request-type', ListValue::FEATURE_REQUEST_TYPE),
                    $this->listCard('projects.list-priority', ListValue::PRIORITY),
                ),
                _Columns(
                    $this->listCard('projects.list-feature-request-status', ListValue::FEATURE_REQUEST_STATUS),
                    $this->listCard('projects.list-suggestion-status', ListValue::SUGGESTION_STATUS),
                ),
                _Columns(
                    $this->listCard('projects.list-team-role', ListValue::TEAM_ROLE),
                    $this->listCard('projects.phases', ListValue::PHASE),
                ),
            )->class('gap-5'),

            _Html('projects.teams')->class('font-semibold text-lg mt-10 mb-2'),
            _Html('projects.teams-hint')->class('text-sm text-gray-500 mb-6'),
            _CardWhiteP4(
                new ProjectTeamsTable(['team_id' => $this->teamId]),
            )->class('border border-gray-200 !p-6 mb-4'),
        );
    }

    protected function listCard($title, string $listKey)
    {
        return _CardWhiteP4(
            _Html($title)->class('font-semibold mb-4'),
            new ListValuesTable([
                'list_key' => $listKey,
                'team_id' => $this->teamId,
            ]),
        )->class('border border-gray-200 !p-6');
    }
}
