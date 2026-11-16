<?php

namespace Condoedge\Projects\Kompo\FeatureRequests;

use Condoedge\Projects\Kompo\Concerns\ChangesPmStatus;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Kompo\Concerns\RemembersPmFilters;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class FeatureRequestsTable extends WhiteTable
{
    use ChangesPmStatus;
    use PmElements;
    use RemembersPmFilters;

    public const ID = 'pm-feature-requests-table';
    public $id = self::ID;

    /** The house way of saying a list is empty, and here, what to do about it. */
    public $noItemsFound = 'projects.empty-feature-requests';

    // Reserve height so the status filter's dropdown has room to open. The tab panel clips with
    // overflow:hidden, so on an empty list — where the table collapses to one "no items" line —
    // the downward menu would spill past the panel's short bottom edge and be cut off.
    public $itemsWrapperStyle = 'min-height: 360px';

    public function authorize()
    {
        return isSuperAdmin();
    }

    /** The filter fields whose value survives a round trip off the board. */
    protected function filterKeys(): array
    {
        return ['search', 'search_status'];
    }

    /** Search and a stage filter, as the suggestions list already had — the two were inconsistent. */
    public function top()
    {
        return _Flex(
            // Marks a filter browse apart from a boot, so a cleared filter can be told from a reload.
            $this->pmFilterMarker(),
            // Defaulted from what was last applied, so returning from a feature shows the same
            // filter the list is actually under rather than a blank control over filtered rows.
            _Input('projects.search')->name('search', false)->class('flex-1 mb-0')
                ->default($this->rememberedFilter('search'))
                ->placeholder('projects.search-feature-requests')->debounce(300)->serverFilter(),
            _Select('projects.status')->name('search_status', false)->class('w-48 mb-0')
                ->default($this->rememberedFilter('search_status'))
                // An explicit empty entry, so a persisted status can be cleared back to all — the
                // options carry no "none" of their own, and the filter now outlives the page.
                ->options(
                    collect(['' => __('projects.all-statuses')])
                        ->union(ListValue::optionsForProject(ListValue::FEATURE_REQUEST_STATUS, $this->prop('project_id')))
                )
                ->placeholder('projects.all-statuses')->serverFilter(),
            _Button('projects.new-feature-request')->icon('plus')
                ->selfGet('getForm')->inModal(),
        )->class('gap-3 !items-end mb-3');
    }

    public function query()
    {
        $search = trim((string) $this->rememberedFilter('search', ''));
        $status = $this->rememberedFilter('search_status');

        return FeatureRequest::asSystemOperation()
            ->when($this->prop('project_id'), fn ($q) => $q->where('project_id', $this->prop('project_id')))
            ->when($status !== null && $status !== '', fn ($q) => $q->where('status', (int) $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('title', 'LIKE', '%'.$search.'%')
                ->orWhere('problem', 'LIKE', '%'.$search.'%')
                ->orWhere('proposed_solution', 'LIKE', '%'.$search.'%')))
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
        // Sortable on the columns backed by a real column; the ⋮ actions column is not. Status,
        // priority and phase sort by their stored value (the list's own order), which is the order
        // the pipeline reads in.
        return [
            _Th('projects.title')->sort('title'),
            _Th('projects.status')->class('w-44')->sort('status'),
            _Th('projects.priority')->class('w-32')->sort('priority'),
            _Th('projects.phase')->class('w-28')->sort('phase'),
            _Th()->class('w-12'),
        ];
    }

    public function render($fr)
    {
        [$owner, $repo] = $fr->githubRepoTarget();

        return _TableRow(
            _Rows(
                // Not a link of its own: the row itself opens the record page, and a link inside it
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

            $this->pmPriorityPill($fr->priority, $fr->id, $fr->team_id, static::ID),

            $this->pmPhasePill($fr->phase, $fr->id, $fr->team_id, static::ID),

            _TripleDotsDropdown(
                _DropdownLink('projects.open')->href('pm.feature', ['id' => $fr->id]),
                _DeleteLink('projects.delete')->class('text-danger')
                    ->deleteTitle('projects.confirm-delete')->confirmMessage('projects.delete')
                    ->selfPost('deleteFr', ['id' => $fr->id])
                    ->refresh(static::ID)->closeConfirmationModalOnClick(),
            ),
        )->href('pm.feature', ['id' => $fr->id])->inNewTab();
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
