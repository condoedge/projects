<?php

namespace Condoedge\Projects\Kompo\Suggestions;

use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\Enums\SuggestionStatusEnum;
use Condoedge\Projects\Models\Suggestion;
use Condoedge\Projects\Kompo\Concerns\ChangesPmStatus;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class SuggestionsTable extends WhiteTable
{
    use ChangesPmStatus;
    use PmElements;

    public const ID = 'pm-suggestions-table';
    public $id = self::ID;

    /** The house way of saying a list is empty, and here, what to do about it. */
    public $noItemsFound = 'projects.empty-suggestions';

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function top()
    {
        return _Rows(
            _Flex(
                _Input('projects.search')->name('search', false)->class('flex-1 mb-0')
                    ->placeholder('projects.search-suggestions')->debounce(300)->serverFilter(),
                _Select('projects.status')->name('search_status', false)->class('w-48 mb-0')
                    ->options(ListValue::optionsForProject(ListValue::SUGGESTION_STATUS, $this->prop("project_id")))
                    ->placeholder('projects.all-statuses')->serverFilter(),
                _Button('projects.new-suggestion')->icon('plus')
                    ->selfGet('getSuggestionForm')->inModal(),
            )->class('gap-3 !items-end mb-3'),
        );
    }

    public function query()
    {
        $search = trim((string) request('search'));
        $status = request('search_status');

        $hasStatusFilter = $status !== null && $status !== '';

        return Suggestion::asSystemOperation()
            ->when($this->prop('project_id'), fn ($q) => $q->where('project_id', $this->prop('project_id')))
            ->when($hasStatusFilter, fn ($q) => $q->where('status', (int) $status))
            // Hide resolved suggestions by default; still reachable via the status filter.
            ->when(! $hasStatusFilter, fn ($q) => $q->unresolved())
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('title', 'LIKE', '%'.$search.'%')
                ->orWhere('body', 'LIKE', '%'.$search.'%')
                ->orWhere('requester_name', 'LIKE', '%'.$search.'%')
                ->orWhere('reference', 'LIKE', '%'.$search.'%')))
            ->with(['addedBy', 'requestedByUser'])
            ->orderByDesc('priority')
            ->orderByDesc('id');
    }

    public function headers()
    {
        return [
            _Th('projects.title'),
            _Th('projects.priority')->class('w-28'),
            _Th('projects.status')->class('w-44'),
            _Th('projects.requester-name')->class('w-40'),
            _Th()->class('w-12'),
        ];
    }

    public function render($suggestion)
    {
        return _TableRow(
            _Html($suggestion->title)->class('font-medium text-level1'),
            $this->priorityStars($suggestion),
            $this->pmStatusPill(
                $suggestion->status,
                $suggestion->id,
                ListValue::SUGGESTION_STATUS,
                $suggestion->team_id,
                static::ID
            ),
            _Html($suggestion->requester_name ?: $suggestion->requestedByUser?->name ?: '—'),
            _TripleDotsDropdown(
                $suggestion->promoted_to_feature_request_id
                    ? _DropdownLink('projects.open-feature-request')->icon('external-link')
                        ->href('pm.feature', ['id' => $suggestion->promoted_to_feature_request_id])
                    : null,
                $suggestion->status?->isKey('PROMOTED')
                    ? null
                    : _DropdownLink('projects.promote')->selfPost('promote', ['id' => $suggestion->id])
                        ->refresh(static::ID),
                $suggestion->status?->isKey('DECLINED')
                    ? null
                    : _DropdownLink('projects.discard')->selfPost('discard', ['id' => $suggestion->id])
                        ->refresh(static::ID),
                _DeleteLink('projects.delete')->class('text-danger')
                    ->deleteTitle('projects.confirm-delete')->confirmMessage('projects.delete')
                    ->selfPost('deleteSuggestion', ['id' => $suggestion->id])
                    ->refresh(static::ID)->closeConfirmationModalOnClick(),
            ),
        )->get('pm.record-drawer', ['kind' => 'suggestion', 'id' => $suggestion->id])->inDrawer();
    }


    /** Clickable 1-3 star priority; click the current level again to clear it. */
    protected function priorityStars($suggestion)
    {
        $current = (int) $suggestion->priority;

        return _Flex(
            collect(range(1, 3))->map(fn ($level) => _Link()
                ->icon('star')
                ->class($level <= $current ? 'text-warning' : 'text-gray-300')
                ->selfPost('setPriority', ['id' => $suggestion->id, 'level' => $level === $current ? 0 : $level])
                ->refresh(static::ID))->all()
        )->class('gap-1');
    }

    protected function pmStatusModel($id)
    {
        return Suggestion::asSystemOperation()->findOrFail($id);
    }

    public function setPriority($id)
    {
        $s = Suggestion::asSystemOperation()->findOrFail($id);
        $s->priority = max(0, min(3, (int) request('level')));
        $s->save();
    }

    public function deleteSuggestion($id)
    {
        Suggestion::asSystemOperation()->findOrFail($id)->delete();
    }


    public function getSuggestionForm($id = null)
    {
        return new SuggestionForm($id, ['project_id' => $this->prop('project_id')]);
    }

    public function promote($id)
    {
        $fr = Suggestion::asSystemOperation()->findOrFail($id)->promote();

        // Land straight on the new feature's workspace — one idea → its work page.
        return redirect()->route('pm.feature', ['id' => $fr->id]);
    }

    public function discard($id)
    {
        $s = Suggestion::asSystemOperation()->findOrFail($id);
        $s->status = SuggestionStatusEnum::DECLINED;
        $s->save();
    }
}
