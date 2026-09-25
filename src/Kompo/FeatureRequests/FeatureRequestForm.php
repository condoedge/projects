<?php

namespace Condoedge\Projects\Kompo\FeatureRequests;

use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\Enums\ComplexityEnum;
use Condoedge\Projects\Models\Enums\ConfidenceEnum;
use Condoedge\Projects\Models\Enums\FeatureRequestStatusEnum;
use Condoedge\Projects\Models\Enums\FeatureRequestTypeEnum;
use Condoedge\Projects\Models\Enums\PriorityEnum;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Utils\Kompo\Common\Modal;

class FeatureRequestForm extends Modal
{
    public $_Title = 'projects.new-feature-request';
    public $class = 'max-w-3xl';
    public $model = FeatureRequest::class;

    // The base Modal adds its own "Sauvegarder" in the header, on top of the save button
    // this form already puts at the bottom. Two buttons for one submission.
    protected $noHeaderButtons = true;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        if ($this->modelKey()) {
            $this->model(FeatureRequest::asSystemOperation()->findOrFail($this->modelKey()));
        }
    }

    public function beforeSave()
    {
        if (!$this->model->id) {
            $this->model->setTeamId();
            $this->model->project_id = $this->prop('project_id');
        }

        // Assemble the JSON app reference from the three inputs.
        $this->model->app_reference = array_filter([
            'module' => request('ar_module'),
            'route' => request('ar_route'),
            'screen' => request('ar_screen'),
        ]);


        // One line = one criterion. The model keeps the ticks already made.
        $this->model->setCriteriaFromLines(request('acceptance_criteria_raw'));

        // Stamp the AI code-review date the first time gap notes are recorded.
        if (trim((string) request('ai_review_notes')) !== '' && !$this->model->ai_reviewed_at) {
            $this->model->ai_reviewed_at = now();
        }
    }

    public function body()
    {
        $model = $this->model instanceof FeatureRequest ? $this->model : new FeatureRequest();
        $projectId = $model->project_id ?: $this->prop('project_id');
        $ref = $model->app_reference ?: [];
        $criteria = $model->criteriaAsLines();

        return _Rows(
            _Columns(
                _Select('projects.type')->name('type')
                    ->options(ListValue::optionsForProject(ListValue::FEATURE_REQUEST_TYPE, $projectId))
                    ->default(FeatureRequestTypeEnum::FEATURE->value),
                _Select('projects.status')->name('status')
                    ->options(ListValue::optionsForProject(ListValue::FEATURE_REQUEST_STATUS, $projectId))
                    ->default(FeatureRequestStatusEnum::DRAFT->value),
                _Select('projects.priority')->name('priority')
                    ->options(ListValue::optionsForProject(ListValue::PRIORITY, $projectId))
                    ->default(PriorityEnum::MEDIUM->value),
            ),
            _Input('projects.title')->name('title')->required()
                ->onEnter(fn ($e) => $e->closeModal()->refresh(FeatureRequestsTable::ID)),
            _Textarea('projects.problem')->name('problem')->rows(3),
            _Textarea('projects.proposed-solution')->name('proposed_solution')->rows(3),

            _Panel(
                _Html('projects.app-reference')->class('font-semibold text-sm mb-1'),
                _Columns(
                    _Input('Module')->name('ar_module', false)->default($ref['module'] ?? null),
                    _Input('Route')->name('ar_route', false)->default($ref['route'] ?? null),
                    _Input('Screen')->name('ar_screen', false)->default($ref['screen'] ?? null),
                ),
            )->class('mb-2'),

            _Textarea('projects.acceptance-criteria')->name('acceptance_criteria_raw', false)
                ->default($criteria)->rows(4)
                ->comment('Given … / When … / Then … — one per line'),

            _Textarea('projects.menu-design')->name('menu_design')->rows(2),

            _MultiFile('projects.attachments')->name('files'),

            // Estimation is hidden while the request is still a draft (new/promoted FR).
            $model->status && !$model->status->isKey("DRAFT")
                ? _Panel(
                    _Html('projects.estimate-notes')->class('font-semibold text-sm mb-1'),
                    _Columns(
                        _InputNumber('projects.effort-days')->name('effort_days')->step(0.5)->min(0),
                        _Select('projects.complexity')->name('complexity')->options(ComplexityEnum::optionsWithLabels()),
                        _Select('projects.confidence')->name('confidence')->options(ConfidenceEnum::optionsWithLabels()),
                    ),
                    _Textarea('projects.estimate-notes')->name('estimate_notes')->rows(2),
                )->class('mb-2')
                : null,

            // AI code-review gaps/answers (step between analysis and "ready"). Hidden while draft.
            $model->status && !$model->status->isKey("DRAFT")
                ? _Textarea('projects.ai-review-notes')->name('ai_review_notes')->rows(3)
                : null,

            _FlexEnd(
                _SubmitButton('projects.save')->alert('projects.saved')->closeModal()->refresh(FeatureRequestsTable::ID),
            ),
        );
    }

    public function rules()
    {
        return [
            'title' => 'required|max:255',
        ];
    }
}
