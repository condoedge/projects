<?php

namespace Condoedge\Projects\Kompo\Projects;

use Condoedge\Projects\Kompo\Concerns\ChangesPmStatus;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Models\Enums\SuggestionStatusEnum;
use Condoedge\Projects\Models\Enums\TaskKindEnum;
use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\Project;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Projects\Models\Suggestion;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * The three stages of the module side by side, instead of behind three tabs.
 *
 * Suggestion → request → task is what this module is for, and the board split it across tabs, so
 * you could never see an idea become work. Here they sit in one view: picking a request lights up
 * the suggestions it came from and narrows the tasks to the ones it produced, which is the
 * relationship the tabs hid.
 *
 * The tabbed board stays — it is the right shape for working inside one stage at length. This is
 * the view for seeing where things stand.
 */
class PipelinePage extends Form
{
    use ChangesPmStatus;
    use PmElements;

    public const ID = 'pm-pipeline';
    public $id = self::ID;
    public $containerClass = 'fullContainer';

    protected ?int $projectId = null;
    protected ?int $focusId = null;

    /**
     * One colour per stage of the flow, taken from the same ramp the lifecycle uses: the blue of
     * what arrives, the mauve of what has been specified, the green of what is being built. The
     * board tints its columns the same way — same language, two renderers, because the board's
     * drag-drop forces it through a Blade view.
     */
    private const STAGE_HEX = [
        'suggestions' => '#0078FF',
        'requests' => '#4E219B',
        'tasks' => '#006241',
    ];

    /**
     * The same three colours blended onto white, as classes rather than inline styles.
     *
     * ->attr(['style' => ...]) is silently dropped on a Kompo container: the attribute never
     * reaches the DOM, which is why the first attempt at this rendered untinted — and why the
     * stepper's discs had to become raw markup. A tint has to arrive as a class. Tailwind
     * generates these arbitrary values because they appear here, in source it scans.
     */
    private const STAGE_WASH = [
        'suggestions' => 'bg-[#DEEDFF]',
        'requests' => 'bg-[#E8E2F2]',
        'tasks' => 'bg-[#DEEBE6]',
    ];

    private const STAGE_TEXT = [
        'suggestions' => 'text-[#0078FF]',
        'requests' => 'text-[#4E219B]',
        'tasks' => 'text-[#006241]',
    ];

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->projectId = (int) ($this->prop('project_id') ?? request('project_id')) ?: null;
        $this->focusId = (int) ($this->prop('focus') ?? request('focus')) ?: null;

        if ($this->focusId) {
            $this->store(['focus' => $this->focusId]);
        }
    }

    public function render()
    {
        $project = $this->projectId ? Project::asSystemOperation()->findOrFail($this->projectId) : null;
        $focus = $this->focusId ? FeatureRequest::asSystemOperation()->find($this->focusId) : null;

        return _Rows(
            $this->pmHeader(
                $project?->name ?: __('projects.all-projects'),
                self::VIEW_PIPELINE,
                $this->projectId,
                $project?->code
            ),

            _Flex(
                $this->suggestionsColumn($focus),
                $this->requestsColumn($focus),
                $this->tasksColumn($focus),
            )->class('!items-start gap-4'),
        );
    }

    protected function suggestionsColumn($focus)
    {
        $linkedIds = $focus
            ? Suggestion::asSystemOperation()->where('promoted_to_feature_request_id', $focus->id)->pluck('id')->all()
            : [];

        $open = Suggestion::asSystemOperation()
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->unresolved()
            ->orderByDesc('priority')->orderBy('id')->limit(8)->get();

        $linked = $linkedIds
            ? Suggestion::asSystemOperation()->whereIn('id', $linkedIds)->get()
            : collect();

        $openTotal = Suggestion::asSystemOperation()
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->unresolved()
            ->count();

        return $this->columnPanel('suggestions', __('projects.suggestions'), (string) $openTotal, _Rows(

            $openTotal === 0 ? null : _Rows(
                _Link('projects.triage-open')->button()->class('w-full')
                    ->href('pm.triage', $this->projectId ? ['project_id' => $this->projectId] : []),
                _Html(__('projects.triage-untouched', ['n' => $this->untouchedCount()]))
                    ->class('text-xs text-graydark text-center mt-1'),
            )->class('mb-3'),

            $linked->isEmpty() ? null : _Rows(
                _Html(__('projects.pipeline-came-from'))->class('text-xs font-semibold text-level1 mb-2'),
                _Rows($linked->map(fn ($s) => $this->miniCard($s->title, $s->reference, true))->all())->class('gap-2 mb-3'),
            ),

            $open->isEmpty() && $linked->isEmpty()
                ? $this->columnEmpty(__('projects.empty-col-suggestions'))
                : _Rows($open->map(fn ($s) => $this->miniCard($s->title, $s->reference, false))->all())->class('gap-2'),
        ));
    }

    protected function requestsColumn($focus)
    {
        $requests = FeatureRequest::asSystemOperation()
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->withTaskProgress()
            ->orderByDesc('id')->get();

        return $this->columnPanel('requests', __('projects.feature-requests'), (string) $requests->count(), _Rows(
            $requests->isEmpty() ? $this->columnEmpty(__('projects.empty-col-requests')) : null,

            _Rows($requests->map(function ($fr) use ($focus) {
                $isFocus = $focus && $focus->id === $fr->id;

                return _Rows(
                    _Link($fr->title)->class('font-medium text-sm leading-snug')
                        ->href('pm.pipeline', array_filter([
                            'project_id' => $this->projectId,
                            'focus' => $isFocus ? null : $fr->id,
                        ])),
                    _Flex(
                        $this->pmStatusPill($fr->status, $fr->id, ListValue::FEATURE_REQUEST_STATUS, $fr->team_id, self::ID),
                        $this->pmPriority($fr->priority),
                    )->class('gap-2 items-center mt-2'),
                    (int) $fr->tasks_count === 0 ? null
                        : _Rows($this->pmProgressOf($fr))->class('mt-2'),
                )->class(
                    'bg-white rounded-lg p-3 border '
                    . ($isFocus ? 'border-2 border-greenmain' : 'border-level5')
                );
            })->all())->class('gap-2'),
        ));
    }

    protected function tasksColumn($focus)
    {
        $tasks = ProjectTask::asSystemOperation()
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->when($focus, fn ($q) => $q->where('feature_request_id', $focus->id))
            ->with('assignee')
            ->orderBy('order')->orderByDesc('id')
            ->limit(20)->get();

        return $this->columnPanel(
            'tasks',
            __('projects.tasks'),
            $focus ? __('projects.pipeline-linked', ['n' => $tasks->count()]) : (string) $tasks->count(),
            _Rows(
                // The same quick capture the tasks table has: seeing what is missing and adding
                // it are the same moment.
                !$this->projectId ? null :
                    _Input()->name('new_title', false)->placeholder('projects.quick-add-task')
                        ->class('mb-3')
                        ->onEnter(fn ($e) => $e->withAllFormValues()
                            ->selfPost('quickAddTask', [
                                'pid' => $this->projectId,
                                'fid' => $focus?->id,
                            ])->alert('projects.saved')->refresh(self::ID)),

                $tasks->isEmpty()
                    ? $this->columnEmpty($focus ? __('projects.empty-col-tasks-focused') : __('projects.empty-col-tasks'))
                    : _Rows($tasks->map(fn ($t) => _Flex(
                        _Html('<span style="width:8px;height:8px;border-radius:2px;display:inline-block;margin-top:6px;background:'
                            . ($t->status?->hex() ?: '#5F5F5F') . '"></span>'),
                        _Rows(
                            _Link($t->title)->class('text-sm leading-snug')->href('pm.task', ['id' => $t->id]),
                            $this->pmDueDate($t->due_date, $t->status === TaskStatusEnum::COMPLETED)->class('text-xs mt-1'),
                        )->class('flex-auto'),
                    )->class('items-start gap-2 bg-white rounded-lg p-3 border border-level5'))->all())->class('gap-2'),
            )
        );
    }

    /** An empty column that says what would fill it, rather than just sitting blank. */
    protected function columnEmpty(string $text)
    {
        return _Html($text)
            ->class('text-sm text-graydark text-center leading-relaxed border border-dashed border-level5 rounded-lg p-4 whitespace-pre-line');
    }

    /** A tinted header and a dot, rather than a hairline nobody reads. */
    protected function columnHeader(string $label, string $count, string $stage)
    {
        return _Flex(
            _Html('<span style="width:9px;height:9px;border-radius:50%;display:inline-block;background:'
                . self::STAGE_HEX[$stage] . '"></span>'),
            _Html($label)->class('text-base font-bold text-level1 flex-auto'),
            _Html($count)->class('text-sm font-bold ' . self::STAGE_TEXT[$stage]),
        )->class('items-center gap-2 px-4 py-3 ' . self::STAGE_WASH[$stage]);
    }

    /** The panel a column sits in: lighter than the page, so it reads as a container. */
    protected function columnPanel(string $stage, string $label, string $count, $body)
    {
        return _Rows(
            $this->columnHeader($label, $count, $stage),
            _Rows($body)->class('p-3 gap-2'),
        )->class('flex-1 min-w-0 rounded-xl overflow-hidden bg-[#FBFDFC] shadow-sm');
    }

    protected function miniCard(string $title, ?string $ref, bool $lit)
    {
        return _Rows(
            !$ref ? null : _Html($ref)->class('text-xs text-graydark mb-1'),
            _Html($title)->class('text-sm leading-snug'),
        )->class('rounded-lg p-3 border ' . ($lit ? 'bg-level4 border-greenmain' : 'bg-white border-level5'));
    }

    protected function untouchedCount(): int
    {
        return Suggestion::asSystemOperation()
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->where('status', SuggestionStatusEnum::NEW->value)
            ->count();
    }

    /** Same contract as the tasks table's quick add, so both behave identically. */
    public function quickAddTask()
    {
        $title = trim((string) request('new_title'));
        $projectId = request('pid');

        if ($title === '' || !$projectId) {
            return;
        }

        $project = Project::asSystemOperation()->find($projectId);

        $task = new ProjectTask();
        $task->setTeamId($project?->team_id);
        $task->project_id = $projectId;
        $task->feature_request_id = request('fid') ?: null;
        $task->title = $title;
        $task->kind = TaskKindEnum::DELIVERABLE;
        $task->status = TaskStatusEnum::PENDING;
        $task->save();
    }

    protected function pmStatusModel($id)
    {
        return FeatureRequest::asSystemOperation()->findOrFail($id);
    }
}
