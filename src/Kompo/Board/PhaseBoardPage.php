<?php

namespace Condoedge\Projects\Kompo\Board;

use Condoedge\Projects\Kompo\Board\Concerns\BoardHeader;
use Condoedge\Projects\Kompo\Concerns\ChangesPmStatus;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\Project;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * One column per phase, each holding the feature requests and tasks tagged with it — the same
 * "where does this stand" question the other boards answer, sliced by phase instead of by status.
 *
 * Scoped to a single project, unlike Kanban/Gantt/Pipeline: a phase is a per-team list (like
 * priority or status), so two teams' "phase #1" can mean two different things. An all-projects
 * board would either mislabel columns or need a column set per team — real complexity nobody
 * asked for yet.
 */
class PhaseBoardPage extends Form
{
    use BoardHeader;
    use ChangesPmStatus;
    use PmElements;

    public const ID = 'pm-phase-board';
    public $id = self::ID;
    public $containerClass = 'fullContainer';

    protected int $projectId;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->projectId = (int) ($this->prop('project_id') ?? request('project_id'));
    }

    public function render()
    {
        $project = Project::asSystemOperation()->findOrFail($this->projectId);

        $phases = ListValue::forList(ListValue::PHASE, (int) $project->team_id);

        $features = FeatureRequest::asSystemOperation()
            ->where('project_id', $this->projectId)
            ->orderByDesc('id')->get();

        $tasks = ProjectTask::asSystemOperation()
            ->where('project_id', $this->projectId)
            ->orderBy('order')->orderByDesc('id')->get();

        return _Rows(
            $this->boardHeader($this->projectId, 'pm.phases'),

            $phases->isEmpty()
                ? _Html('projects.no-phases-yet')->class('text-sm text-graydark text-center p-8')
                : _Flex(
                    // The "sans phase" bucket rides along as a last, unnamed column — same
                    // shape as every named one, built from the same phaseColumn().
                    ...$phases->map(fn ($phase) => $this->phaseColumn($phase, $features, $tasks))
                        ->push($this->phaseColumn(null, $features, $tasks))
                        ->all(),
                )->class('!items-start gap-4 overflow-x-auto'),
        );
    }

    /** $phase === null draws the "sans phase" bucket, the same columns as any other. */
    protected function phaseColumn($phase, $features, $tasks)
    {
        $value = $phase?->value;

        $colFeatures = $features->filter(fn ($fr) => $fr->phase?->value === $value)->values();
        $colTasks = $tasks->filter(fn ($t) => $t->phase?->value === $value)->values();

        return _Rows(
            _Flex(
                $phase
                    ? $this->pmTint(_Pill($phase->displayName()), $phase->displayColor())
                    : _Html(__('projects.no-phase'))->class('text-sm font-semibold text-graydark'),
                _Html((string) ($colFeatures->count() + $colTasks->count()))
                    ->class('text-sm font-bold text-graydark ml-auto'),
            )->class('items-center gap-2 px-4 py-3 bg-[#FBFDFC]'),

            _Rows(
                $colFeatures->isEmpty() && $colTasks->isEmpty()
                    ? _Html('projects.empty-col-phase')
                        ->class('text-sm text-graydark text-center border border-dashed border-level5 rounded-lg p-4')
                    : null,

                ...$colFeatures->map(fn ($fr) => $this->featureCard($fr))->all(),
                ...$colTasks->map(fn ($t) => $this->taskCard($t))->all(),
            )->class('p-3 gap-2'),
        )->class('flex-1 min-w-[260px] rounded-xl overflow-hidden bg-[#FBFDFC] shadow-sm');
    }

    protected function featureCard(FeatureRequest $fr)
    {
        return _Rows(
            _Link($fr->title)->class('font-medium text-sm leading-snug')->href('pm.feature', ['id' => $fr->id]),
            _Flex(
                $this->pmStatusPill($fr->status, $fr->id, ListValue::FEATURE_REQUEST_STATUS, $fr->team_id, self::ID),
                $this->pmPriority($fr->priority),
            )->class('gap-2 items-center mt-2'),
        )->class('bg-white rounded-lg p-3 border border-level5');
    }

    protected function taskCard(ProjectTask $t)
    {
        return _Flex(
            _Html('<span style="width:8px;height:8px;border-radius:2px;display:inline-block;margin-top:6px;background:'
                . ($t->status?->hex() ?: '#5F5F5F') . '"></span>'),
            _Rows(
                _Link($t->title)->class('text-sm leading-snug')->href('pm.task', ['id' => $t->id]),
                $this->pmDueDate($t->due_date, $t->status === TaskStatusEnum::COMPLETED)->class('text-xs mt-1'),
            )->class('flex-auto'),
        )->class('items-start gap-2 bg-white rounded-lg p-3 border border-level5');
    }

    protected function pmStatusModel($id)
    {
        return FeatureRequest::asSystemOperation()->findOrFail($id);
    }
}
