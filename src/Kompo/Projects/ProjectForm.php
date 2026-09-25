<?php

namespace Condoedge\Projects\Kompo\Projects;

use Condoedge\Projects\Kompo\Settings\ProjectTeamsTable;
use Condoedge\Projects\Kompo\Settings\ListValuesTable;
use Condoedge\Projects\Models\Enums\PriorityEnum;
use Condoedge\Projects\Models\Enums\ProjectStatusEnum;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\Project;
use Condoedge\Utils\Kompo\Common\Modal;

class ProjectForm extends Modal
{
    public $_Title = 'projects.new-project';
    public $model = Project::class;

    // Set by the host through the store, the way TeamForm is embedded in TeamSettingsPage.
    protected $isPage = false;

    // The base Modal adds its own "Sauvegarder" in the header, on top of the save button this
    // form already puts at the bottom. As a tab the header is hidden but the button stayed in
    // the page; dropping it removes both.
    protected $noHeaderButtons = true;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        // super_admin manages every team → load cross-team records (Kompo binds with team scope otherwise).
        if ($this->modelKey()) {
            $this->model(Project::asSystemOperation()->findOrFail($this->modelKey()));
        }

        $this->isPage = (bool) ($this->prop('is_page') ?? false);

        $this->setPageStylesIfRequired();
    }

    // Strips the modal chrome so the same form can sit inside a tab. Same four levers
    // App\Kompo\Teams\TeamForm turns; they live on Condoedge\Utils\Kompo\Common\Modal.
    protected function setPageStylesIfRequired()
    {
        if (!$this->isPage) {
            return;
        }

        $this->_Title = null;
        $this->class = '';
        $this->style = 'max-height: none;';
        $this->headerClass = 'hidden';
        // Horizontal padding only: the tab bar already sets the form off from the top, and the
        // sibling tabs hold full-bleed tables that would look inset with vertical padding.
        $this->bodyWrapperClass = '!px-6 !py-0 mt-2';
    }

    public function beforeSave()
    {
        if (!$this->model->id) {
            $this->model->setTeamId();
        }
    }

    public function body()
    {
        // Enter already submits; closing on it too is what stops a second save from the button
        // afterwards. Only as a modal — as a tab there is nothing to close.
        $nameInput = _Input('projects.name')->name('name')->required();

        if (!$this->isPage) {
            $nameInput->onEnter(fn ($e) => $e->closeModal()->refresh(ProjectsTable::ID));
        }

        return _Rows(
            $nameInput,
            _Textarea('projects.description')->name('description'),
            _Columns(
                _Select('projects.status')->name('status')->options(ProjectStatusEnum::optionsWithLabels())
                    ->default(ProjectStatusEnum::ACTIVE->value),
                _Select('projects.priority')->name('priority')
                    ->options(ListValue::optionsForProject(ListValue::PRIORITY, $this->model->id))
                    ->default(PriorityEnum::MEDIUM->value),
            ),
            _Columns(
                _Date('projects.start-date')->name('start_date'),
                _Date('projects.end-date')->name('end_date'),
            ),
            _Columns(
                _Input('GitHub owner')->name('github_owner')->placeholder(config('projects.github.owner')),
                _Input('GitHub repo')->name('github_repo')->placeholder(config('projects.github.repo')),
            ),
            $this->metadataCard(),
            $this->submitButton(),
            $this->configurableLists(),
        );
    }

    /**
     * The dropdowns the team can shape for itself. Team-scoped, not project-scoped: editing here
     * changes every project of this team, which is what was asked for.
     */
    protected function configurableLists()
    {
        if (!$this->isPage || !$this->model->id) {
            return null;
        }

        $teamId = (int) $this->model->team_id;

        return _Rows(
            _Html('projects.configurable-lists')->class('font-semibold text-lg mt-8 mb-1'),
            _Html('projects.configurable-lists-hint')->class('text-sm text-gray-500 mb-4'),

            _Columns(
                $this->listCard('projects.list-feature-request-type', ListValue::FEATURE_REQUEST_TYPE, $teamId),
                $this->listCard('projects.list-priority', ListValue::PRIORITY, $teamId),
            ),
            _Columns(
                $this->listCard('projects.list-feature-request-status', ListValue::FEATURE_REQUEST_STATUS, $teamId),
                $this->listCard('projects.list-suggestion-status', ListValue::SUGGESTION_STATUS, $teamId),
            ),
            _Columns(
                $this->listCard('projects.list-team-role', ListValue::TEAM_ROLE, $teamId),
                _Html(''),
            ),

            _Html('projects.teams')->class('font-semibold text-lg mt-8 mb-1'),
            _Html('projects.teams-hint')->class('text-sm text-gray-500 mb-4'),
            _CardWhiteP4(
                new ProjectTeamsTable(['team_id' => $teamId]),
            )->class('border border-gray-200 mb-4'),
        );
    }

    protected function listCard($title, string $listKey, int $teamId)
    {
        return _CardWhiteP4(
            _Html($title)->class('font-semibold mb-3'),
            new ListValuesTable([
                'list_key' => $listKey,
                'team_id' => $teamId,
            ]),
        )->class('border border-gray-200 mb-4');
    }

    // Closing and refreshing the projects table only makes sense from the modal: as a tab,
    // neither is on screen.
    protected function submitButton()
    {
        return _FlexEnd(
            $this->isPage
                ? _SubmitButton('projects.save')->alert('projects.saved')
                : _SubmitButton('projects.save')->alert('projects.saved')->closeModal()->refresh(ProjectsTable::ID),
        );
    }

    // Audit trail, read-only. Worth the space in a tab, noise in a creation modal.
    protected function metadataCard()
    {
        if (!$this->isPage || !$this->model->id) {
            return null;
        }

        return _CardWhiteP4(
            _Html('projects.details')->class('font-semibold mb-3'),
            _Columns(
                $this->metadataLine('projects.team', $this->model->teamName()),
                $this->metadataLine('projects.created-at', $this->model->created_at?->translatedFormat('d M Y H:i')),
                $this->metadataLine('projects.updated-at', $this->model->updated_at?->translatedFormat('d M Y H:i')),
            ),
            _Columns(
                $this->metadataLine('projects.added-by', $this->model->addedBy?->name),
                $this->metadataLine('projects.modified-by', $this->model->modifiedBy?->name),
                // Holds the third slot so this row lines up with the one above; _Columns splits
                // evenly across whatever it is given, and nulls are dropped rather than kept.
                _Html(''),
            ),
        )->class('border border-gray-200 mt-4');
    }

    protected function metadataLine($label, $value)
    {
        return _Rows(
            _Html($label)->class('text-sm text-gray-500'),
            _Html($value ?: '—')->class('font-medium'),
        );
    }

    public function rules()
    {
        return [
            'name' => 'required|max:255',
        ];
    }
}
