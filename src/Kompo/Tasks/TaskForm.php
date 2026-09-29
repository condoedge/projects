<?php

namespace Condoedge\Projects\Kompo\Tasks;

use Condoedge\Projects\Kompo\Concerns\SearchesUsers;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\Enums\PriorityEnum;
use Condoedge\Projects\Models\Enums\TaskKindEnum;
use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Utils\Kompo\Common\Modal;
use Kompo\Auth\Facades\UserModel;

class TaskForm extends Modal
{
    use SearchesUsers;

    public $_Title = 'projects.new-task';
    public $class = 'max-w-2xl';
    public $model = ProjectTask::class;

    // The base Modal adds its own "Sauvegarder" in the header, on top of the "Enregistrer" this
    // form already puts at the bottom. Two save buttons for one form; the header one goes.
    protected $noHeaderButtons = true;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        if ($this->modelKey()) {
            $this->model(ProjectTask::asSystemOperation()->findOrFail($this->modelKey()));
        }
    }

    public function beforeSave()
    {
        if (!$this->model->id) {
            $this->model->setTeamId();
            $this->model->project_id = $this->prop('project_id');
            if ($this->prop('feature_request_id')) {
                $this->model->feature_request_id = $this->prop('feature_request_id');
            }
        }

        // One line = one criterion. The model keeps the ticks already made.
        $this->model->setCriteriaFromLines(request('acceptance_criteria_raw'));
    }

    public function body()
    {
        $model = $this->model instanceof ProjectTask ? $this->model : new ProjectTask();
        $projectId = $model->project_id ?: $this->prop('project_id');
        $criteria = $model->criteriaAsLines();

        return _Rows(
            // Enter already submits this form on its own — adding submit() here saved the task
            // twice. So this only closes and refreshes, and lets the existing submission save.
            // Left off the textareas on purpose: there, Enter belongs to the text.
            _Input('projects.title')->name('title')->required()
                ->onEnter(fn ($e) => $e->closeModal()->refresh(TasksTable::ID)),
            _Columns(
                _Select('projects.task-kind')->name('kind')->options(TaskKindEnum::optionsWithLabels())
                    ->default(TaskKindEnum::DELIVERABLE->value),
                _Select('projects.status')->name('status')->options(TaskStatusEnum::optionsWithLabels())
                    ->default(TaskStatusEnum::PENDING->value),
            ),
            _Columns(
                _Select('projects.priority')->name('priority')
                    ->options(ListValue::optionsForProject(ListValue::PRIORITY, $projectId))
                    ->default(PriorityEnum::MEDIUM->value),
                _Select('projects.phase')->name('phase')
                    ->options(ListValue::optionsForProject(ListValue::PHASE, $projectId))
                    ->placeholder('projects.no-phase'),
            ),
            _Textarea('projects.description')->name('description')->rows(3),
            _Textarea('projects.acceptance-criteria')->name('acceptance_criteria_raw', false)
                ->default($criteria)->rows(3)
                ->comment('projects.acceptance-criteria-hint'),
            _Select('projects.feature-requests')->name('feature_request_id')
                ->options(FeatureRequest::asSystemOperation()->where('project_id', $projectId)->pluck('title', 'id'))
                ->comment('Optional — link this task to the feature it delivers'),
            _Select('projects.assignee')->name('assignee_user_id')
                ->searchOptions(2, 'searchUsers', 'retrieveUser'),
            _Columns(
                _Date('projects.start-date')->name('start_date'),
                _Date('projects.due-date')->name('due_date'),
            ),
            _Columns(
                _InputNumber('projects.estimate-days')->name('estimate_days')->step(0.5)->min(0),
                _InputNumber('projects.completion')->name('completion_pct')->min(0)->max(100)->default(0),
            ),
            // Reads "Sous-tâche de" on screen while the relation underneath stays `dependencies`:
            // the direction is the same either way — picking X here puts this task under X, both
            // in the dependency graph and in the tree TasksTable draws from it.
            _MultiSelect('projects.dependencies')->name('dependencies')
                ->options($this->dependencyOptions($projectId)),
            _MultiFile('projects.attachments')->name('files'),
            _FlexEnd(
                _SubmitButton('projects.save')->alert('projects.saved')->closeModal()->refresh(TasksTable::ID),
            ),
        );
    }

    protected function dependencyOptions($projectId)
    {
        $selfId = $this->model instanceof ProjectTask ? $this->model->id : null;

        return ProjectTask::asSystemOperation()->where('project_id', $projectId)
            ->when($selfId, fn ($q) => $q->where('id', '!=', $selfId))
            ->pluck('title', 'id');
    }

    public function rules()
    {
        return [
            'title' => 'required|max:255',
        ];
    }
}
