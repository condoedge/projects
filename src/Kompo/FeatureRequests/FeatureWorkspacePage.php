<?php

namespace Condoedge\Projects\Kompo\FeatureRequests;

use Condoedge\Projects\Kompo\Concerns\ChangesPmStatus;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Kompo\Concerns\ReadOnlyWorkspace;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Kompo\Tasks\TasksTable;
use Condoedge\Projects\Models\Enums\ComplexityEnum;
use Condoedge\Projects\Models\Enums\ConfidenceEnum;
use Condoedge\Projects\Models\Enums\FeatureRequestStatusEnum;
use Condoedge\Projects\Models\Enums\FeatureRequestTypeEnum;
use Condoedge\Projects\Models\Enums\PriorityEnum;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * One page per feature: guided stage stepper + inline autosaving documentation + its tasks.
 * Replaces the modal-heavy flow — no pop-up to edit, no tab hopping.
 */
class FeatureWorkspacePage extends Form
{
    use ChangesPmStatus;
    use PmElements;
    use ReadOnlyWorkspace;

    public const ID = 'pm-feature-workspace';
    public $id = self::ID;
    public $containerClass = 'fullContainer';
    public $model = FeatureRequest::class;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        // super_admin works across teams → load bypassing the team scope.
        if ($this->modelKey()) {
            $this->model(FeatureRequest::asSystemOperation()->with(['project', 'addedBy'])->findOrFail($this->modelKey()));
        }

        $this->readEditMode();
    }

    public function beforeSave()
    {
        // Both of these exist in edit mode alone. Rewriting them unconditionally let any save
        // from the read view blank them — silently, since neither is validated.
        if (request()->has('ar_module')) {
            $this->model->app_reference = array_filter([
                'module' => request('ar_module'),
                'route' => request('ar_route'),
                'screen' => request('ar_screen'),
            ]);
        }

        if (request()->has('acceptance_criteria_raw')) {
            // One line = one criterion. The model keeps the ticks already made, by text.
            $this->model->setCriteriaFromLines(request('acceptance_criteria_raw'));
        }

        if (trim((string) request('ai_review_notes')) !== '' && !$this->model->ai_reviewed_at) {
            $this->model->ai_reviewed_at = now();
        }
    }

    public function render()
    {
        $fr = $this->model;

        return _Rows(
            $this->workspaceHeader('pm.feature', $fr->id, 'pm.project-board', ['project_id' => $fr->project_id]),

            // The stepper stays in both modes: it is where the request stands, not a field.
            _CardWhite(
                $this->pmStepper(
                    $fr->status,
                    $fr->id,
                    FeatureRequestStatusEnum::pipeline(),
                    self::ID
                ),
                // The pill is the control here too, so the stage changes the same way on the
                // record's own page as it does in every list.
                // The pill stays beside the stepper: the stepper walks the pipeline, the pill
                // also reaches the stages that sit outside it — rejection above all.
                _Flex(
                    $this->pmStatusPill(
                        $fr->status,
                        $fr->id,
                        ListValue::FEATURE_REQUEST_STATUS,
                        $fr->team_id,
                        self::ID
                    ),
                    !$this->editing ? null :
                        _Html('projects.autosave-hint')->class('text-xs text-graydark ml-3'),
                )->class('mt-4 items-center'),
            )->p4()->class('mt-4'),

            $this->workspaceColumns(
                [
                    $this->documentationBlock($fr),
                    $this->criteriaBlock($fr),
                    $this->referenceBlock($fr),
                    $this->tasksBlock($fr),
                ],
                [
                    $this->detailsBlock($fr),
                    $this->estimationBlock($fr),
                    $this->actionsBlock($fr),
                    // The same context the drawer shows, so opening the full page adds to it
                    // rather than losing it.
                    _CardWhite($this->pmContextSections($fr))->p4(),
                ],
            ),
        );
    }

    // ── BLOCKS ──

    protected function documentationBlock($fr)
    {
        return $this->infoBlock(
            'projects.problem',
            $this->editing
                ? _Rows(
                    $this->auto(_Textarea('projects.problem')->name('problem')->rows(4)),
                    $this->auto(_Textarea('projects.proposed-solution')->name('proposed_solution')->rows(4)),
                    $this->auto(_Textarea('projects.menu-design')->name('menu_design')->rows(3)),
                    _MultiFile('projects.attachments')->name('files'),
                )
                : _Rows(
                    $this->readText($fr->problem),
                    _Html('projects.proposed-solution')->class('text-sm text-gray-500 mt-3'),
                    $this->readText($fr->proposed_solution),
                    !$fr->menu_design ? null : _Rows(
                        _Html('projects.menu-design')->class('text-sm text-gray-500 mt-3'),
                        $this->readText($fr->menu_design),
                    ),
                ),
        );
    }

    /** Ticked as a list, edited one line per criterion. */
    protected function criteriaBlock($fr)
    {
        return $this->infoBlock(
            trim(__('projects.acceptance-criteria') . ' ' . $fr->criteriaProgress()),
            $this->editing
                ? $this->auto(_Textarea()->name('acceptance_criteria_raw', false)
                    ->default($fr->criteriaAsLines())->rows(6))
                    ->comment('projects.acceptance-criteria-hint')
                : $this->criteriaChecklist($fr, self::ID),
        );
    }
    protected function referenceBlock($fr)
    {
        $ref = $fr->app_reference ?: [];

        return $this->infoBlock(
            'projects.app-reference',
            $this->editing
                ? _Columns(
                    $this->auto(_Input('Module')->name('ar_module', false)->default($ref['module'] ?? null)),
                    $this->auto(_Input('Route')->name('ar_route', false)->default($ref['route'] ?? null)),
                    $this->auto(_Input('Screen')->name('ar_screen', false)->default($ref['screen'] ?? null)),
                )
                : _Rows(
                    $this->detailRow('Module', $ref['module'] ?? null),
                    $this->detailRow('Route', $ref['route'] ?? null),
                    $this->detailRow('Screen', $ref['screen'] ?? null),
                ),
        );
    }

    protected function tasksBlock($fr)
    {
        return $this->infoBlock(
            'projects.tasks',
            _LazyComponent(fn () => new TasksTable([
                'project_id' => $fr->project_id,
                'feature_request_id' => $fr->id,
            ])),
        );
    }

    protected function detailsBlock($fr)
    {
        return $this->infoBlock(
            'projects.details',
            $this->editing
                ? _Rows(
                    $this->autoSel(_Select('projects.type')->name('type')
                        ->options(ListValue::optionsForProject(ListValue::FEATURE_REQUEST_TYPE, $fr->project_id))),
                    $this->autoSel(_Select('projects.priority')->name('priority')
                        ->options(ListValue::optionsForProject(ListValue::PRIORITY, $fr->project_id))),
                )
                : _Rows(
                    $this->detailRow('projects.type', $fr->type ? _Pill($fr->type->label())
                        ->class(($fr->type->displayColor() ?: 'bg-gray-400') . ' text-white') : null),
                    $this->detailRow('projects.status', $fr->status ? _Pill($fr->status->label())
                        ->class($fr->status->displayColor() . ' text-white') : null),
                    $this->detailRow('projects.priority', $fr->priority ? _Pill($fr->priority->label())
                        ->class($fr->priority->displayColor() . ' text-white') : null),
                ),
        );
    }

    /** Hidden while the request is still a draft, as it was before. */
    protected function estimationBlock($fr)
    {
        if (!$fr->status || $fr->status->isKey('DRAFT')) {
            return null;
        }

        return $this->infoBlock(
            'projects.estimate-notes',
            $this->editing
                ? _Rows(
                    $this->auto(_InputNumber('projects.effort-days')->name('effort_days')->step(0.5)->min(0)),
                    $this->autoSel(_Select('projects.complexity')->name('complexity')
                        ->options(ComplexityEnum::optionsWithLabels())),
                    $this->autoSel(_Select('projects.confidence')->name('confidence')
                        ->options(ConfidenceEnum::optionsWithLabels())),
                    $this->auto(_Textarea('projects.estimate-notes')->name('estimate_notes')->rows(3)),
                    $this->auto(_Textarea('projects.ai-review-notes')->name('ai_review_notes')->rows(4)),
                )
                : _Rows(
                    $this->detailRow('projects.effort-days', $fr->effort_days),
                    $this->detailRow('projects.complexity', $fr->complexity?->label()),
                    $this->detailRow('projects.confidence', $fr->confidence?->label()),
                    _Html('projects.estimate-notes')->class('text-sm text-gray-500 mt-3'),
                    $this->readText($fr->estimate_notes),
                    _Html('projects.ai-review-notes')->class('text-sm text-gray-500 mt-3'),
                    $this->readText($fr->ai_review_notes),
                ),
        );
    }

    /** Exports and the GitHub push — actions on the record, available in both modes. */
    protected function actionsBlock($fr)
    {
        if (!$fr->status || $fr->status->isKey('DRAFT')) {
            return null;
        }

        return $this->infoBlock(
            'projects.actions',
            _Rows(
                _Button('projects.export-for-estimation')->outlined()
                    ->selfGet('getEstimationExport')->inModal(),
                _Button('projects.ai-review-export')->outlined()
                    ->selfGet('getAiReviewExport')->inModal(),
                config('projects.github.token')
                    ? _Button('projects.sync-github')->outlined()
                        ->selfPost('syncGithub')->alert('projects.sync-queued')
                    : _Button('projects.sync-github')->outlined()
                        ->alert('projects.github-not-configured'),
            )->class('gap-2'),
        );
    }

    protected function pmStatusModel($id)
    {
        return FeatureRequest::asSystemOperation()->findOrFail($id);
    }

    public function syncGithub()
    {
        \Condoedge\Projects\Jobs\PushIssueToGithub::dispatch('feature_request', $this->model->id);
    }

    public function getEstimationExport()
    {
        return new EstimationExportModal($this->model->id);
    }

    public function getAiReviewExport()
    {
        return new AiReviewExportModal($this->model->id);
    }
}
