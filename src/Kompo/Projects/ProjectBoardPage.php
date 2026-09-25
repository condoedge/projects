<?php

namespace Condoedge\Projects\Kompo\Projects;

use Condoedge\Projects\Kompo\FeatureRequests\FeatureRequestsTable;
use Condoedge\Projects\Kompo\Suggestions\SuggestionsTable;
use Condoedge\Projects\Kompo\Tasks\TasksTable;
use Condoedge\Projects\Models\Project;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Utils\Kompo\Common\Form;

class ProjectBoardPage extends Form
{
    use PmElements;

    public $containerClass = 'fullContainer';

    protected ?int $projectId = null;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        // Kompo exposes route path params via prop() (e.g. EventRegistrationPeriodForm reads prop('event_id')).
        $this->projectId = (int) ($this->prop('project_id') ?? request('project_id'));
    }

    public function render()
    {
        $project = Project::asSystemOperation()->findOrFail($this->projectId);
        $store = ['project_id' => $this->projectId];

        return _Rows(
            $this->pmHeader($project->name, self::VIEW_TABS, $this->projectId),

            // Lazy, not eager: _Tabs builds every tab on every render, so standing on Tâches was
            // also mounting the settings tab and its six nested tables. _LazyTabs loads only the
            // tab being shown — the same reason TeamSettingsPage uses it.
            _LazyTabs(
                _LazyTab(
                    fn () => new FeatureRequestsTable($store),
                )->label('projects.feature-requests'),
                _LazyTab(
                    fn () => new SuggestionsTable($store),
                )->label('projects.suggestions'),
                _LazyTab(
                    fn () => new TasksTable($store),
                )->label('projects.tasks'),
                _LazyTab(
                    fn () => new ProjectForm($this->projectId, ['is_page' => true]),
                )->label('projects.settings'),
            ),
        );
    }
}
