<?php

namespace Condoedge\Projects\Kompo\Projects;

use Condoedge\Projects\Kompo\Concerns\PmElements;
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
            _LazyComponent(fn () => new ProjectsTable()),
        );
    }
}
