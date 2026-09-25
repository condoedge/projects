<?php

namespace Condoedge\Projects\Kompo\Tasks;

use Condoedge\Projects\Jobs\PushIssueToGithub;
use Condoedge\Projects\Models\Enums\TaskKindEnum;
use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Projects\Kompo\Concerns\ChangesPmStatus;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class TasksTable extends WhiteTable
{
    use ChangesPmStatus;
    use PmElements;

    public const ID = 'pm-tasks-table';
    public $id = self::ID;

    /** The house way of saying a list is empty, and here, what to do about it. */
    public $noItemsFound = 'projects.empty-tasks';

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function top()
    {
        // Bake project/feature ids into the action params at render time (prop is reliable here).
        $ids = [
            'pid' => $this->prop('project_id'),
            'fid' => $this->prop('feature_request_id'),
        ];

        return _Flex(
            // Quick capture now commits on Enter. It used to hang off the button, which returned
            // silently whenever the field was empty — so the button read as doing nothing at all.
            _Input()->name('new_title', false)->placeholder('projects.quick-add-task')
                ->class('flex-1 mb-0')
                ->onEnter(fn ($e) => $e->withAllFormValues()
                    ->selfPost('quickAdd', $ids)->alert('projects.saved')->refresh(static::ID)),

            _Select()->name('new_kind', false)->options(TaskKindEnum::optionsWithLabels())
                ->default(TaskKindEnum::DELIVERABLE->value)->class('w-40 mb-0'),

            // The button opens the full form, the way every other "add" in SISC does.
            _Button('projects.add-task')->icon('plus')
                ->selfGet('getForm')->inModal(),
        )->class('gap-2 !items-end mb-3');
    }

    public function query()
    {
        $tasks = ProjectTask::asSystemOperation()
            ->when($this->prop('project_id'), fn ($q) => $q->where('project_id', $this->prop('project_id')))
            ->when($this->prop('feature_request_id'), fn ($q) => $q->where('feature_request_id', $this->prop('feature_request_id')))
            ->with(['assignee', 'dependencies'])
            ->orderBy('order')->orderByDesc('id')
            ->get();

        return $this->asTree($tasks);
    }

    /**
     * Lays the "depends on" links out as a tree: a task sits under the one it depends on.
     *
     * Two things this has to survive, because dependencies are a graph and not a tree. A task may
     * depend on several others — it is drawn under the first of them, so it appears exactly once
     * rather than being duplicated down the table. And a cycle would otherwise recurse forever,
     * so anything already placed is skipped; a task caught in one still shows, at the root.
     */
    protected function asTree($tasks)
    {
        $byId = $tasks->keyBy('id');

        $parentOf = [];
        $childrenOf = [];

        foreach ($tasks as $task) {
            $parent = $task->dependencies->sortBy('id')->first(fn ($d) => $byId->has($d->id));
            $parentOf[$task->id] = $parent?->id;

            if ($parent) {
                $childrenOf[$parent->id][] = $task->id;
            }
        }

        $ordered = collect();
        $placed = [];

        $walk = function ($id, $depth) use (&$walk, $byId, $childrenOf, $ordered, &$placed) {
            if (isset($placed[$id])) {
                return;
            }

            $placed[$id] = true;
            $task = $byId->get($id);
            $task->tree_depth = $depth;
            $ordered->push($task);

            foreach ($childrenOf[$id] ?? [] as $childId) {
                $walk($childId, $depth + 1);
            }
        };

        // Roots first, keeping the table's own ordering between them.
        foreach ($tasks as $task) {
            if (!$parentOf[$task->id]) {
                $walk($task->id, 0);
            }
        }

        // Then whatever a cycle kept out of the walk above.
        foreach ($tasks as $task) {
            $walk($task->id, 0);
        }

        return $ordered;
    }

    public function headers()
    {
        return [
            _Th('projects.title'),
            _Th('projects.task-kind')->class('w-24'),
            _Th('projects.status')->class('w-40'),
            _Th('projects.priority')->class('w-32'),
            _Th('projects.assignee')->class('w-36'),
            _Th('projects.due-date')->class('w-32'),
            _Th()->class('w-12'),
        ];
    }

    public function render($task)
    {
        return _TableRow(
            // Indented by depth, with a branch mark so a child reads as belonging to the line
            // above it rather than as a stray offset row.
            _Flex(
                !$task->tree_depth ? null :
                    _Html('└')->class('text-gray-400 select-none')
                        ->style('margin-left: ' . (($task->tree_depth - 1) * 18) . 'px'),
                _Html($task->title)->class('font-medium'),
            )->class('gap-2 items-center'),
            $task->kind ? _Pill($task->kind->label())->class($task->kind->color().' text-white') : _Html('—'),
            $this->pmEnumStatusPill($task->status, $task->id, TaskStatusEnum::class, static::ID),
            $this->pmPriority($task->priority),
            _Html($task->assignee?->name ?? '—'),
            $this->pmDueDate($task->due_date, $task->status === TaskStatusEnum::COMPLETED),
            _TripleDotsDropdown(
                _DropdownLink('projects.open')->href('pm.task', ['id' => $task->id]),
                // The quick toggle stays beside it: marking a task done is one click, not a form.
                $task->status === TaskStatusEnum::COMPLETED
                    ? _DropdownLink('↺ '.TaskStatusEnum::PENDING->label())->selfPost('setStatus', ['id' => $task->id, 'status' => TaskStatusEnum::PENDING->value])->refresh(static::ID)
                    : _DropdownLink('✓ '.TaskStatusEnum::COMPLETED->label())->selfPost('setStatus', ['id' => $task->id, 'status' => TaskStatusEnum::COMPLETED->value])->refresh(static::ID),
                config('projects.github.token')
                    ? _DropdownLink('projects.sync-github')->selfPost('syncGithub', ['id' => $task->id])->refresh(static::ID)->alert('projects.sync-queued')
                    : _DropdownLink('projects.sync-github')->alert('projects.github-not-configured'),
                _DeleteLink('projects.delete')->class('text-danger')
                    ->deleteTitle('projects.confirm-delete')->confirmMessage('projects.delete')
                    ->selfPost('deleteTask', ['id' => $task->id])
                    ->refresh(static::ID)->closeConfirmationModalOnClick(),
            ),
        )->get('pm.record-drawer', ['kind' => 'task', 'id' => $task->id])->inDrawer();
    }


    public function deleteTask($id)
    {
        ProjectTask::asSystemOperation()->findOrFail($id)->delete();
    }


    public function getForm($id = null)
    {
        return new TaskForm($id, [
            'project_id' => $this->prop('project_id'),
            'feature_request_id' => $this->prop('feature_request_id'),
        ]);
    }

    public function quickAdd()
    {
        $title = trim((string) request('new_title'));
        $projectId = request('pid') ?: $this->prop('project_id');
        if ($title === '' || !$projectId) {
            return;
        }
        $project = \Condoedge\Projects\Models\Project::asSystemOperation()->find($projectId);
        $t = new ProjectTask();
        $t->setTeamId($project?->team_id);
        $t->project_id = $projectId;
        $t->feature_request_id = request('fid') ?: $this->prop('feature_request_id');
        $t->title = $title;
        $t->kind = TaskKindEnum::tryFrom((int) request('new_kind')) ?? TaskKindEnum::DELIVERABLE;
        $t->status = TaskStatusEnum::PENDING;
        $t->save();
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

    public function setStatus($id)
    {
        $task = ProjectTask::asSystemOperation()->findOrFail($id);
        $status = TaskStatusEnum::tryFrom((int) request('status'));
        if ($status) {
            $task->status = $status;
            if ($status === TaskStatusEnum::COMPLETED) {
                $task->completion_pct = 100;
            }
            $task->save();
        }
    }

    public function syncGithub($id)
    {
        PushIssueToGithub::dispatch('task', (int) $id);
    }
}
