<?php

namespace Condoedge\Projects\Kompo\Tasks;

use Condoedge\Projects\Kompo\Concerns\ChangesPmStatus;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Kompo\Concerns\ReadOnlyWorkspace;
use Condoedge\Projects\Kompo\Concerns\SearchesUsers;
use Condoedge\Projects\Models\Enums\TaskKindEnum;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * One page per task: read-only by default, editable behind the Modifier button — the shape
 * App\Kompo\Events\Activity\ActivityPage uses, down to the level1 heading rule and the
 * content / sidebar split.
 *
 * Edit mode is the same route with ?edit=1 rather than a second page, so the two views cannot
 * drift apart and a link to a task always lands somewhere readable.
 */
class TaskWorkspacePage extends Form
{
    use SearchesUsers;
    use ChangesPmStatus;
    use PmElements;
    use ReadOnlyWorkspace;

    public const ID = 'pm-task-workspace';
    public $id = self::ID;
    public $containerClass = 'fullContainer';
    public $model = ProjectTask::class;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        // super_admin works across teams → load bypassing the team scope.
        if ($this->modelKey()) {
            $this->model(ProjectTask::asSystemOperation()->with(['project', 'addedBy'])->findOrFail($this->modelKey()));
        }

        $this->readEditMode();
    }

    public function beforeSave()
    {
        // Only when the field was actually submitted. It exists in edit mode alone, so rewriting
        // unconditionally let any save from the read view wipe the criteria — and a save that
        // carried a stale copy of the form could drop the ticks.
        if (request()->has('acceptance_criteria_raw')) {
            // One line = one criterion. The model keeps the ticks already made, by text.
            $this->model->setCriteriaFromLines(request('acceptance_criteria_raw'));
        }
    }

    public function render()
    {
        $task = $this->model;

        return _Rows(
            $this->workspaceHeader('pm.task', $task->id, 'pm.project-board', ['project_id' => $task->project_id]),

            // The status band the other two detail pages carry.
            _CardWhite(
                $this->pmEnumStatusPill($task->status, $task->id, TaskStatusEnum::class, self::ID),
            )->p4()->class('mt-4'),
            $this->workspaceColumns(
                [$this->descriptionBlock($task), $this->criteriaBlock($task), $this->subTasksBlock($task)],
                [$this->detailsBlock($task), $this->planningBlock($task), $this->linksBlock($task),
                    // The same context the drawer shows, so opening the full page adds to it
                    // rather than losing it.
                    _CardWhite($this->pmContextSections($task))->p4(),
                ],
            ),
        );
    }

    protected function pmStatusModel($id)
    {
        return ProjectTask::asSystemOperation()->findOrFail($id);
    }

    /** A task that becomes complete also owes its completion percentage. */
    protected function applyPmStatus($task, int $value): void
    {
        $status = TaskStatusEnum::tryFrom($value);

        if (!$status) {
            return;
        }

        $task->status = $status;

        if ($status === TaskStatusEnum::COMPLETED) {
            $task->completion_pct = 100;
        }

        $task->save();
    }

    // ── BLOCKS ──

    protected function descriptionBlock(ProjectTask $task)
    {
        return $this->infoBlock(
            'projects.description',
            $this->editing
                ? $this->auto(_Textarea()->name('description')->rows(5))
                : $this->readText($task->description),
        );
    }

    /**
     * Ticked as a list, edited one line per criterion. The done states survive an edit as long
     * as the wording does — HasAcceptanceCriteria matches them back on the text.
     */
    protected function criteriaBlock(ProjectTask $task)
    {
        return $this->infoBlock(
            trim(__('projects.acceptance-criteria') . ' ' . $task->criteriaProgress()),
            $this->editing
                ? $this->auto(_Textarea()->name('acceptance_criteria_raw', false)
                    ->default($task->criteriaAsLines())->rows(6))
                    ->comment('projects.acceptance-criteria-hint')
                : $this->criteriaChecklist($task, self::ID),
        );
    }

    /**
     * Children only, and read-only in both modes: a task's parent is set on the child's own page,
     * through "Sous-tâche de" in the details block. Editing children from here would be editing
     * other records than the one this page is about.
     */
    protected function subTasksBlock(ProjectTask $task)
    {
        $children = $task->dependents;

        return $this->infoBlock(
            'projects.sub-tasks',
            $children->isEmpty()
                ? $this->readText(null)
                : _Rows($children->map(fn ($child) => _Link($child->title)
                    ->icon('arrow-right')
                    ->href('pm.task', ['id' => $child->id])
                    ->class('block py-1 underline'))->all()),
        );
    }

    protected function detailsBlock(ProjectTask $task)
    {
        return $this->infoBlock(
            'projects.details',
            $this->editing
                ? _Rows(
                    $this->autoSel(_Select('projects.task-kind')->name('kind')
                        ->options(TaskKindEnum::optionsWithLabels())),
                    $this->autoSel(_Select('projects.priority')->name('priority')
                        ->options(ListValue::optionsForProject(ListValue::PRIORITY, $task->project_id))),
                    $this->autoSel(_Select('projects.assignee')->name('assignee_user_id')
                        ->searchOptions(2, 'searchUsers', 'retrieveUser')),
                    // "Sous-tâche de" — this task's parents, which is what drives the tree.
                    $this->autoSel(_MultiSelect('projects.dependencies')->name('dependencies')
                        ->options($this->dependencyOptions($task))),
                )
                : _Rows(
                    $this->detailRow('projects.task-kind', $task->kind
                        ? _Pill($task->kind->label())->class($task->kind->color() . ' text-white')
                        : null),
                    $this->detailRow('projects.status', $task->status
                        ? _Pill($task->status->label())->class($task->status->color() . ' text-white')
                        : null),
                    $this->detailRow('projects.priority', $task->priority
                        ? _Pill($task->priority->label())
                            ->class($task->priority->displayColor() . ' text-white')
                        : null),
                    $this->detailRow('projects.assignee', $task->assignee?->name),
                    $this->detailRow('projects.dependencies', $task->dependencies->isEmpty()
                        ? null
                        : _Rows($task->dependencies->map(fn ($p) => _Link($p->title)
                            ->href('pm.task', ['id' => $p->id])->class('underline block'))->all())),
                ),
        );
    }

    protected function planningBlock(ProjectTask $task)
    {
        return $this->infoBlock(
            'projects.planning',
            $this->editing
                ? _Rows(
                    $this->auto(_Date('projects.start-date')->name('start_date')),
                    $this->auto(_Date('projects.due-date')->name('due_date')),
                    $this->auto(_InputNumber('projects.estimate-days')->name('estimate_days')->step(0.5)->min(0)),
                    $this->auto(_InputNumber('projects.completion')->name('completion_pct')->min(0)->max(100)),
                )
                : _Rows(
                    $this->detailRow('projects.start-date', $task->start_date?->format('Y-m-d')),
                    $this->detailRow('projects.due-date', $task->due_date?->format('Y-m-d')),
                    $this->detailRow('projects.estimate-days', $task->estimate_days),
                    $this->detailRow('projects.completion', $task->completion_pct . '%'),
                ),
        );
    }

    protected function linksBlock(ProjectTask $task)
    {
        return $this->infoBlock(
            'projects.feature-requests',
            $this->editing
                ? _Rows(
                    $this->autoSel(_Select()->name('feature_request_id')
                        ->options(FeatureRequest::asSystemOperation()
                            ->where('project_id', $task->project_id)->pluck('title', 'id'))),
                    _MultiFile('projects.attachments')->name('files'),
                )
                : _Rows(
                    $task->featureRequest
                        ? _Link($task->featureRequest->title)->icon('arrow-right')
                            ->href('pm.feature', ['id' => $task->featureRequest->id])->class('underline')
                        : $this->readText(null),
                ),
        );
    }

    /** Everything else on the project, minus this task — it cannot be its own sub-task. */
    protected function dependencyOptions(ProjectTask $task)
    {
        return ProjectTask::asSystemOperation()
            ->where('project_id', $task->project_id)
            ->where('id', '!=', $task->id)
            ->pluck('title', 'id');
    }
}
