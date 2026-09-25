<?php

namespace Condoedge\Projects\Kompo\Board;

use Condoedge\Projects\Kompo\Board\Concerns\BoardHeader;
use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Utils\Kompo\Common\Form;

class KanbanPage extends Form
{
    use BoardHeader;
    use PmElements;

    public $containerClass = 'fullContainer';

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function render()
    {
        $projectId = request('project_id');

        $tasks = ProjectTask::asSystemOperation()
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->with(['project:id,name', 'assignee:id,name', 'featureRequest:id,title'])
            ->orderBy('order')->orderByDesc('id')
            ->get();

        // Columns are always the statuses. Grouping adds swimlanes on top of them: one board
        // per feature request, so a board holding several features stops reading as one flat
        // pile in which nothing says what belongs with what.
        $groupBy = request('group_by') === 'feature' ? 'feature' : 'none';

        $columnsFor = fn ($set) => collect(TaskStatusEnum::cases())->map(fn ($s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'hex' => $s->hex(),
            'tasks' => $set->filter(fn ($t) => $t->status?->value === $s->value)->values(),
        ]);

        $lanes = $groupBy === 'feature'
            ? $tasks->groupBy('feature_request_id')->map(fn ($set, $frId) => [
                'label' => $set->first()?->featureRequest?->title ?: __('projects.no-feature-request'),
                'columns' => $columnsFor($set),
            ])->values()
            : collect([['label' => null, 'columns' => $columnsFor($tasks)]]);

        return _Rows(
            $this->boardHeader($projectId, 'pm.board'),

            _Flex(
                _Html('projects.group-by')->class('text-sm text-graydark'),
                $this->groupTab('none', 'projects.group-none', $groupBy, $projectId),
                $this->groupTab('feature', 'projects.group-feature', $groupBy, $projectId),
            )->class('gap-2 items-center mb-3'),

            // One hidden Kompo link per task, carrying the drawer interaction.
            //
            // The board's cards are raw markup — the native drag-drop needs inline on* handlers,
            // which Kompo elements do not emit — so a card cannot hold an interaction of its own.
            // It clicks one of these instead: the link is a real Kompo element, so Kompo's own
            // handler runs and fills the drawer, and the card stays draggable.
            _Rows(
                ...$tasks->map(fn ($t) => _Link('')
                    ->id('pm-drawer-' . $t->id)
                    ->get('pm.record-drawer', ['kind' => 'task', 'id' => $t->id])
                    ->inDrawer())->all()
            )->class('hidden'),

            _Html(view('projects::kanban', [
                'lanes' => $lanes,
                'projectId' => $projectId,
                'statusUrl' => route('pm.task-status'),
                'csrf' => csrf_token(),
            ])->render()),
        );
    }

    protected function groupTab(string $value, string $label, string $current, $projectId)
    {
        $on = $current === $value;

        return _Link($label)
            ->class('text-sm px-3 py-1 rounded-full border '
                . ($on ? 'bg-level4 border-greenmain text-greenmain font-semibold' : 'bg-white border-level5 text-graydark'))
            ->href('pm.board', array_filter([
                'project_id' => $projectId,
                'group_by' => $value === 'none' ? null : $value,
            ]));
    }
}
