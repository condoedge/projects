<?php

namespace Condoedge\Projects\Kompo\FeatureRequests;

use Condoedge\Projects\Kompo\Concerns\ChangesPmStatus;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class FeatureRequestsTable extends WhiteTable
{
    use ChangesPmStatus;
    use PmElements;

    public const ID = 'pm-feature-requests-table';
    public $id = self::ID;

    /** The house way of saying a list is empty, and here, what to do about it. */
    public $noItemsFound = 'projects.empty-feature-requests';

    public function authorize()
    {
        return isSuperAdmin();
    }

    /** Search and a stage filter, as the suggestions list already had — the two were inconsistent. */
    public function top()
    {
        return _Flex(
            _Input('projects.search')->name('search', false)->class('flex-1 mb-0')
                ->placeholder('projects.search-feature-requests')->debounce(300)->serverFilter(),
            _Select('projects.status')->name('search_status', false)->class('w-48 mb-0')
                ->options(ListValue::optionsForProject(ListValue::FEATURE_REQUEST_STATUS, $this->prop('project_id')))
                ->placeholder('projects.all-statuses')->serverFilter(),
            _Button('projects.new-feature-request')->icon('plus')
                ->selfGet('getForm')->inModal(),
        )->class('gap-3 !items-end mb-3');
    }

    public function query()
    {
        $search = trim((string) request('search'));
        $status = request('search_status');

        return FeatureRequest::asSystemOperation()
            ->when($this->prop('project_id'), fn ($q) => $q->where('project_id', $this->prop('project_id')))
            ->when($status !== null && $status !== '', fn ($q) => $q->where('status', (int) $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('title', 'LIKE', '%'.$search.'%')
                ->orWhere('problem', 'LIKE', '%'.$search.'%')
                ->orWhere('proposed_solution', 'LIKE', '%'.$search.'%')))
            ->withTaskProgress()
            ->orderByDesc('id');
    }

    /**
     * Columns weighted by what varies.
     *
     * Type held "Fonctionnalité" on every row, Créé par the same name, Issue GitHub an em dash —
     * three columns of width for no signal, while the title, the only thing that differs, was
     * squeezed into the narrowest cell and wrapped over five lines. Type and author moved to the
     * record's own page; the GitHub link rides along with the title, when there is one.
     */
    public function headers()
    {
        return [
            _Th('projects.title'),
            _Th('projects.status')->class('w-44'),
            _Th('projects.priority')->class('w-32'),
            _Th('projects.tasks')->class('w-28'),
            _Th()->class('w-12'),
        ];
    }

    public function render($fr)
    {
        [$owner, $repo] = $fr->githubRepoTarget();

        return _TableRow(
            _Rows(
                // Not a link any more: the row itself opens the drawer, and a link inside it
                // swallowed the click over the whole title cell.
                _Html($fr->title)->class('font-medium text-level1'),
                !$fr->github_issue_number ? null :
                    _Link('#'.$fr->github_issue_number)->class('text-xs text-graydark mt-1')
                        ->href('https://github.com/'.$owner.'/'.$repo.'/issues/'.$fr->github_issue_number)
                        ->target('_blank'),
            ),

            $this->pmStatusPill(
                $fr->status,
                $fr->id,
                ListValue::FEATURE_REQUEST_STATUS,
                $fr->team_id,
                static::ID
            ),

            $this->pmPriority($fr->priority),

            $this->pmProgressOf($fr, __('projects.no-task-yet')),

            _TripleDotsDropdown(
                _DropdownLink('projects.open')->href('pm.feature', ['id' => $fr->id]),
                _DeleteLink('projects.delete')->class('text-danger')
                    ->deleteTitle('projects.confirm-delete')->confirmMessage('projects.delete')
                    ->selfPost('deleteFr', ['id' => $fr->id])
                    ->refresh(static::ID)->closeConfirmationModalOnClick(),
            ),
        )->get('pm.record-drawer', ['kind' => 'feature', 'id' => $fr->id])->inDrawer();
    }


    public function getForm($id = null)
    {
        return new FeatureRequestForm($id, ['project_id' => $this->prop('project_id')]);
    }

    protected function pmStatusModel($id)
    {
        return FeatureRequest::asSystemOperation()->findOrFail($id);
    }

    public function deleteFr($id)
    {
        FeatureRequest::asSystemOperation()->findOrFail($id)->delete();
    }
}
