<?php

namespace Condoedge\Projects\Kompo\Projects;

use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Kompo\Settings\TeamSettingsTab;
use Condoedge\Utils\Kompo\Common\Form;

class ProjectsPage extends Form
{
    use PmElements;

    public $containerClass = 'fullContainer';

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function render()
    {
        return _Rows(
            _Flex(
                _Rows(
                    _PageTitle('projects.menu-projects'),
                    // Landing here without knowing the model left three words - suggestions,
                    // demandes, taches - with nothing tying them together. One sentence, once,
                    // where everyone starts.
                    _Html('projects.intro')->class('text-sm text-graydark mt-1'),
                )->class('flex-1'),
                // No back link here: this page is where back leads. Same switcher as everywhere
                // else, with nothing marked — none of these views is the list itself.
                $this->pmViewSwitcher('list'),
            )->class('items-center gap-3 mb-4'),

            // Lazy, not eager, for the same reason ProjectBoardPage's tabs are: mounting the
            // settings tab's six nested tables just to show the projects list would cost every
            // visit here six extra queries nobody asked for.
            _LazyTabs(
                _LazyTab(fn () => new ProjectsTable())->label('projects.projects'),
                _LazyTab(fn () => new TeamSettingsTab())->label('projects.team-settings'),
            ),
        );
    }
}
