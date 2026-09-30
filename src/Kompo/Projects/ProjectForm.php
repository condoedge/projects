<?php

namespace Condoedge\Projects\Kompo\Projects;

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
            _Columns(
                $nameInput,
                _Input('projects.project-code')->name('code'),
            ),
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
            $this->teamSettingsHint(),
        );
    }

    /**
     * Points at TeamSettingsTab (on the project list, ProjectsPage) instead of holding the
     * configurable lists and working teams itself. Those are shaped by the whole SISC team, not
     * by one project — editing "Priorités" from this project used to silently reshape every
     * other project's dropdown too, since there never was a per-project list to begin with.
     */
    protected function teamSettingsHint()
    {
        if (!$this->isPage || !$this->model->id) {
            return null;
        }

        return _Rows(
            _Html('projects.team-settings-moved')->class('text-sm text-graydark mt-8'),
            _Link('projects.projects')->href('pm.projects')->class('text-sm underline'),
        );
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
