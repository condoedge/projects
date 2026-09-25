<?php

namespace Condoedge\Projects\Kompo\Suggestions;

use Condoedge\Projects\Kompo\Concerns\PmElements;

use Condoedge\Projects\Models\Enums\SuggestionStatusEnum;
use Condoedge\Projects\Models\Project;
use Condoedge\Projects\Models\Suggestion;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * Triage, one suggestion at a time.
 *
 * The list could search and filter, but sorting 179 ideas through a table means opening a ⋮ menu
 * per row: the decision — promote, decline, move on — was four gestures deep on the job this
 * module does most. Here the suggestion fills the page and each decision is one button.
 *
 * It also surfaces near-duplicates before the decision rather than after. Left alone, a backlog
 * this size ends up with the same request promoted three times under three titles, and that is
 * expensive to unpick later.
 */
class TriagePage extends Form
{
    use PmElements;

    public const ID = 'pm-triage';

    /** How many of the queue are drawn; the rest are counted, not listed. */
    private const QUEUE_SHOWN = 40;
    public $id = self::ID;
    public $containerClass = 'fullContainer';

    protected ?int $projectId = null;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->projectId = (int) ($this->prop('project_id') ?? request('project_id')) ?: null;
    }

    public function render()
    {
        $project = $this->projectId ? Project::asSystemOperation()->find($this->projectId) : null;
        $current = $this->currentSuggestion();
        $counts = $this->counts();

        return _Rows(
            $this->pmHeader(
                __('projects.triage-title'),
                self::VIEW_PIPELINE,
                $this->projectId
            ),

            _Flex(
                _Html('')->class('flex-auto'),
                $this->progressHeader($counts),
            )->class('items-center mb-4'),

            !$current
                ? $this->emptyState()
                : _Flex(
                    _Rows(
                        $this->suggestionCard($current),
                        $this->duplicatesCard($current),
                        $this->actionBar($current),
                    )->class('flex-auto gap-4'),
                    _Rows($this->queueCard($current))->class('w-[320px] shrink-0'),
                )->class('!items-start gap-6'),
        );
    }

    // ── PIECES ──

    protected function progressHeader(array $counts)
    {
        $total = $counts['total'];
        $done = $counts['done'];
        $pct = $total === 0 ? 1 : $done / $total;

        return _Flex(
            _Html(__('projects.triage-progress', ['done' => $done, 'total' => $total]))
                ->class('text-sm text-graydark whitespace-nowrap'),
            _Rows(_ProgressBar($pct, 'bg-greenmain'))->class('shrink-0 w-40'),
        )->class('items-center gap-3');
    }

    protected function suggestionCard($s)
    {
        return _CardWhite(
            _Flex(
                !$s->reference ? null :
                    _Pill($s->reference)->class('bg-level5 text-level1'),
                _Html(__('projects.triage-meta', [
                    'who' => $s->requester_name ?: ($s->requestedByUser?->name ?: __('projects.unknown-requester')),
                    'when' => $s->created_at?->diffForHumans() ?: '',
                ]))->class('text-sm text-graydark'),
            )->class('items-center gap-3 mb-3'),

            _Html($s->title)->class('text-xl font-bold text-level1 mb-3'),

            !$s->body ? null : _Html(nl2br(e($s->body)))->class('text-base leading-relaxed'),
        )->p4();
    }

    /**
     * Near-duplicates, found on the words of the title.
     *
     * Deliberately a plain overlap on words of four letters or more rather than anything
     * cleverer: it has to run on every keystroke of the queue without a search index, and a
     * false positive here costs a glance, not a wrong decision — a human still chooses.
     */
    protected function duplicatesCard($s)
    {
        $similar = $this->similarTo($s);

        if ($similar->isEmpty()) {
            return null;
        }

        return _CardWhite(
            _Html(__('projects.triage-duplicates', ['n' => $similar->count()]))
                ->class('text-sm font-semibold text-warningdark mb-2'),
            _Rows(
                $similar->map(fn ($other) => _Flex(
                    _Html($other->reference ?: '#'.$other->id)
                        ->class('text-xs text-graydark font-semibold w-16 shrink-0'),
                    _Link($other->title)->class('text-sm flex-auto')
                        ->href('pm.suggestion', ['id' => $other->id]),
                )->class('items-start gap-2'))->all()
            )->class('gap-2'),

            // Spotting duplicates without being able to act on them only moves the work
            // elsewhere: the point of seeing them here is to close them here.
            _Button(__('projects.triage-merge', ['n' => $similar->count() + 1]))
                ->outlined()->class('mt-3 w-min whitespace-nowrap')->id('pm-act-merge')
                ->selfPost('mergeDuplicates', [
                    'id' => $s->id,
                    'ids' => $similar->pluck('id')->implode(','),
                ])->refresh(self::ID),
        )->p4()->class('border-l-4 border-warning');
    }

    protected function actionBar($s)
    {
        return _CardWhite(
            _Flex(
                _Button('projects.promote')->icon('arrow-right')->id('pm-act-promote')
                    ->selfPost('promoteCurrent', ['id' => $s->id]),
                _Button('projects.discard')->outlined()->id('pm-act-decline')
                    ->selfPost('declineCurrent', ['id' => $s->id])->refresh(self::ID),
                _Html('')->class('flex-auto'),
                _Link('projects.triage-skip')->icon('arrow-right-1')->class('text-graydark')->id('pm-act-skip')
                    ->selfPost('skipCurrent', ['id' => $s->id])->refresh(self::ID),
            )->class('items-center gap-3'),

            $this->keyboardLayer(),
        )->p4();
    }

    /**
     * The keyboard, which is the whole point of this screen: sorting 179 ideas one decision at a
     * time only pays if the decision is a keystroke.
     *
     * The listener is installed from an inline handler rather than a <script> tag, because Kompo
     * injects this html through v-html and a script element would never execute. It binds once —
     * a flag on window survives the page's redraws, which would otherwise stack one listener per
     * refresh — and stands down while a field has focus, so typing in the search box never fires
     * a decision.
     */
    protected function keyboardLayer()
    {
        $js = 'if(!window.__pmTriageKeys){window.__pmTriageKeys=1;'
            . 'document.addEventListener("keydown",function(e){'
            . 'var t=e.target;if(t&&(t.tagName==="INPUT"||t.tagName==="TEXTAREA"||t.isContentEditable))return;'
            . 'if(e.metaKey||e.ctrlKey||e.altKey)return;'
            . 'var m={"p":"pm-act-promote","d":"pm-act-decline","f":"pm-act-merge"};'
            . 'var id=m[e.key.toLowerCase()];'
            . 'if(e.key==="ArrowRight"){id="pm-act-skip";}'
            . 'if(!id)return;'
            . 'var el=document.getElementById(id);'
            . 'if(el){e.preventDefault();el.click();}'
            . '});}';

        return _Rows(
            _Html(
                '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="'
                . ' alt="" width="1" height="1" style="position:absolute;opacity:0"'
                . ' onload=\'' . $js . '\'>'
            ),
            _Flex(
                _Html(__('projects.shortcut-promote'))->class('text-xs text-graydark'),
                _Html(__('projects.shortcut-merge'))->class('text-xs text-graydark'),
                _Html(__('projects.shortcut-decline'))->class('text-xs text-graydark'),
                _Html(__('projects.shortcut-skip'))->class('text-xs text-graydark'),
            )->class('gap-4 mt-3 flex-wrap'),
        );
    }

    /**
     * The queue, readable and reachable.
     *
     * Three things it was not. Its rows were inert text, so the only way to learn what an entry
     * said was to decide your way down to it. It stopped at ten with nothing saying another
     * hundred and sixty waited behind. And it could not scroll, so the tenth row was simply the
     * end of the world.
     */
    protected function queueCard($current)
    {
        $remaining = $this->queueQuery()->where('id', '!=', $current->id)->count();

        $queue = $this->queueQuery()
            ->where('id', '!=', $current->id)
            ->limit(self::QUEUE_SHOWN)
            ->get();

        $untouched = Suggestion::asSystemOperation()
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->where('status', SuggestionStatusEnum::NEW->value)
            ->count();

        return _CardWhite(
            _Rows(
                _Flex(
                    _Html(__('projects.triage-queue'))->class('text-lg text-level1 flex-auto'),
                    _Html((string) $remaining)->class('text-sm font-semibold text-graydark'),
                )->class('items-center gap-2'),
                // What the queue's length never said: how much of it nobody has even opened.
                _Html(__('projects.triage-untouched', ['n' => $untouched]))->class('text-xs text-graydark'),
            )->class('border-b border-gray-600/30 mb-3 pb-1'),

            $queue->isEmpty()
                ? _Html(__('projects.triage-queue-empty'))->class('text-sm text-graydark')
                : _Rows(
                    ...$queue->map(fn ($s) => _Flex(
                        _Html($s->reference ?: '#'.$s->id)->class('text-xs text-graydark w-14 shrink-0'),
                        _Html($s->title)->class('text-sm flex-auto'),
                    )
                        ->class('items-start gap-2 py-2 px-2 -mx-2 rounded border-b border-level5 cursor-pointer hover:bg-level5')
                        // The same drawer the lists open: reading an entry must not cost a decision.
                        ->get('pm.record-drawer', ['kind' => 'suggestion', 'id' => $s->id])
                        ->inDrawer())->all()
                )
                    // A fixed height is what makes it a queue rather than a wall: it scrolls, and
                    // the panel keeps the shape of the page around it.
                    ->class('max-h-[420px] overflow-y-auto pr-1'),

            $remaining <= self::QUEUE_SHOWN ? null :
                _Html(__('projects.triage-queue-more', ['n' => $remaining - self::QUEUE_SHOWN]))
                    ->class('text-xs text-graydark mt-3 pt-2 border-t border-level5'),
        )->p4();
    }

    protected function emptyState()
    {
        return _CardWhite(
            _Html(__('projects.triage-done-title'))->class('text-xl font-bold text-level1 mb-2'),
            _Html(__('projects.triage-done-body'))->class('text-base text-graydark'),
        )->p4()->class('text-center');
    }

    // ── QUERIES ──

    /** Oldest first: the queue is a backlog, so what has waited longest goes first. */
    protected function queueQuery()
    {
        $skipped = (array) session('pm_triage_skipped', []);

        return Suggestion::asSystemOperation()
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->unresolved()
            ->whereNotIn('id', $skipped)
            ->orderByDesc('priority')
            ->orderBy('id');
    }

    protected function currentSuggestion()
    {
        return $this->queueQuery()->with('requestedByUser')->first();
    }

    protected function similarTo($s)
    {
        $words = collect(preg_split('/\W+/u', mb_strtolower((string) $s->title)))
            ->filter(fn ($w) => mb_strlen($w) >= 4)
            ->unique()
            ->take(6);

        if ($words->isEmpty()) {
            return collect();
        }

        return Suggestion::asSystemOperation()
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->where('id', '!=', $s->id)
            ->whereNotIn('status', [SuggestionStatusEnum::DECLINED->value])
            ->where(function ($q) use ($words) {
                foreach ($words as $w) {
                    $q->orWhere('title', 'LIKE', '%'.$w.'%');
                }
            })
            ->limit(3)
            ->get();
    }

    protected function counts(): array
    {
        $base = fn () => Suggestion::asSystemOperation()
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId));

        $total = (clone $base())->count();
        $open = (clone $base())->unresolved()->count();

        return ['total' => $total, 'done' => $total - $open];
    }

    // ── ACTIONS ──

    public function promoteCurrent($id)
    {
        $fr = Suggestion::asSystemOperation()->findOrFail($id)->promote();

        // Straight onto the work page it just became: the decision and its consequence in one move.
        return redirect()->route('pm.feature', ['id' => $fr->id]);
    }

    public function declineCurrent($id)
    {
        $s = Suggestion::asSystemOperation()->findOrFail($id);
        $s->status = SuggestionStatusEnum::DECLINED;
        $s->save();
    }

    /**
     * Folds the near-duplicates into the one kept.
     *
     * No merged_into column exists, and inventing one is a migration this does not need: the
     * duplicates are declined and the kept one's reference is written into their notes, so the
     * trail survives in what the schema already has.
     */
    public function mergeDuplicates($id)
    {
        $kept = Suggestion::asSystemOperation()->findOrFail($id);

        $ids = array_filter(explode(',', (string) request('ids')));

        foreach (Suggestion::asSystemOperation()->whereIn('id', $ids)->get() as $dup) {
            $dup->status = SuggestionStatusEnum::DECLINED;
            $dup->internal_notes = trim(($dup->internal_notes ? $dup->internal_notes."\n" : '')
                . __('projects.merged-into', ['ref' => $kept->reference ?: '#'.$kept->id]));
            $dup->save();
        }
    }

    /** Skipping is for this sitting only — it must not look like a decision that was made. */
    public function skipCurrent($id)
    {
        $skipped = (array) session('pm_triage_skipped', []);
        $skipped[] = (int) $id;
        session(['pm_triage_skipped' => array_unique($skipped)]);
    }
}
