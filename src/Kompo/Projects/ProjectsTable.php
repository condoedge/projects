<?php

namespace Condoedge\Projects\Kompo\Projects;

use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Models\Enums\ProjectStatusEnum;
use Condoedge\Projects\Models\Project;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class ProjectsTable extends WhiteTable
{
    use PmElements;

    public const ID = 'pm-projects-table';
    public $id = self::ID;

    /** The house way of saying a list is empty, and here, what to do about it. */
    public $noItemsFound = 'projects.empty-projects';

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function top()
    {
        $scope = request('scope') ?: 'active';

        return _Rows(
            _Flex(
                _Input('projects.search')->name('search', false)->class('flex-1 mb-0')
                    ->placeholder('projects.search-projects')->debounce(300)->serverFilter(),
                _Button('projects.new-project')->icon('plus')
                    ->selfGet('getProjectForm')->inModal(),
            )->class('gap-3 !items-end'),

            // Archived projects are hidden by default: a list of what is live is the useful
            // default, and the count beside it says how much that default is holding back.
            _Flex(
                _Html(__('projects.n-projects', ['n' => $this->countFor($scope)]))
                    ->class('text-sm text-graydark mr-2'),
                $this->scopeTab('active', 'projects.scope-active', $scope),
                $this->scopeTab('archived', 'projects.scope-archived', $scope),
                $this->scopeTab('all', 'projects.scope-all', $scope),
            )->class('gap-2 items-center mt-3'),
        )->class('mb-3');
    }

    protected function scopeTab(string $value, string $label, string $current)
    {
        $on = $current === $value;

        return _Link($label)
            ->class('text-sm px-3 py-1 rounded-full border cursor-pointer '
                . ($on ? 'bg-level4 border-greenmain text-greenmain font-semibold' : 'bg-white border-level5 text-graydark'))
            ->selfGet('setScope', ['scope' => $value])->refresh(self::ID);
    }

    public function setScope($scope = null)
    {
        request()->merge(['scope' => $scope ?: request('scope')]);

        return $this;
    }

    protected function countFor(string $scope): int
    {
        return Project::asSystemOperation()
            ->when($scope === 'active', fn ($q) => $q->where('status', ProjectStatusEnum::ACTIVE->value))
            ->when($scope === 'archived', fn ($q) => $q->where('status', ProjectStatusEnum::ARCHIVED->value))
            ->count();
    }

    public function query()
    {
        $search = trim((string) request('search'));

        // super_admin-only module → show every team's projects (bypass team read-scope).
        $scope = request('scope') ?: 'active';

        return Project::asSystemOperation()
            ->when($scope === 'active', fn ($q) => $q->where('status', ProjectStatusEnum::ACTIVE->value))
            ->when($scope === 'archived', fn ($q) => $q->where('status', ProjectStatusEnum::ARCHIVED->value))
            ->when($search !== '', fn ($q) => $q->where('name', 'LIKE', '%'.$search.'%'))
            ->withTaskProgress()
            ->withTaskAlerts()
            ->withCount([
                'featureRequests',
                // What is actually waiting on a human: the resolved ones are not.
                'suggestions as suggestions_open_count' => fn ($q) => $q->unresolved(),
            ])
            ->orderByDesc('id');
    }

    /**
     * Counts replaced by a state.
     *
     * "179 / 3 / 9" was three raw numbers: none of them said whether anything was finished, and
     * nothing on the row said what needed a decision. The progress bar answers the first, and
     * the last column carries only what is actually wrong — it stays empty on a healthy project.
     */
    public function headers()
    {
        return [
            _Th('projects.name'),
            _Th('projects.progress')->class('w-40'),
            _Th('projects.to-triage')->class('w-28'),
            _Th('projects.feature-requests')->class('w-28'),
            _Th('projects.needs-attention')->class('w-52'),
            _Th()->class('w-12'),
        ];
    }

    public function render($project)
    {
        return _TableRow(
            _Rows(
                _Link($project->name)->class('font-medium text-level1')
                    ->href('pm.project-board', ['project_id' => $project->id]),
                _Html($project->status?->label())->class('text-xs text-graydark mt-1'),
            ),

            $this->pmProgressOf($project, __('projects.no-task-yet')),

            (int) $project->suggestions_open_count === 0
                ? _Html('—')->class('text-sm text-graydark')
                : _Link((string) $project->suggestions_open_count)
                    ->class('text-sm font-semibold text-level1 underline')
                    ->href('pm.triage', ['project_id' => $project->id]),

            _Html((string) $project->feature_requests_count)->class('text-sm'),

            $this->attentionCell($project),

            _TripleDotsDropdown(
                _DropdownLink('projects.save')->selfGet('getProjectForm', ['id' => $project->id])->inModal(),
                _DropdownLink('projects.board')->href('pm.project-board', ['project_id' => $project->id]),
                _DeleteLink('projects.delete')->class('text-danger')
                    ->deleteTitle('projects.confirm-delete')->confirmMessage('projects.delete')
                    ->selfPost('deleteProject', ['id' => $project->id])
                    ->refresh(self::ID)->closeConfirmationModalOnClick(),
            ),
        );
    }

    /** Silent when nothing is wrong — an empty cell is the good news. */
    protected function attentionCell($project)
    {
        $blocked = (int) $project->tasks_blocked_count;
        $late = (int) $project->tasks_late_count;

        // Silence read as "no data loaded" rather than "nothing wrong": saying it plainly is
        // what makes the column trustworthy the rest of the time.
        if (!$blocked && !$late) {
            return _Flex(
                _Html('')->class('bg-positive w-2 h-2 rounded-full shrink-0'),
                _Html('projects.nothing-blocked')->class('text-sm text-graydark'),
            )->class('gap-2 items-center');
        }

        return _Flex(
            !$blocked ? null : _Pill(__('projects.n-blocked', ['n' => $blocked]))
                ->class('bg-dangerlight text-dangerdark whitespace-nowrap'),
            !$late ? null : _Pill(__('projects.n-late', ['n' => $late]))
                ->class('bg-warninglight text-warningdark whitespace-nowrap'),
        )->class('gap-2 items-center');
    }

    public function getProjectForm($id = null)
    {
        return new ProjectForm($id);
    }

    public function deleteProject($id)
    {
        Project::asSystemOperation()->findOrFail($id)->delete();
    }
}
