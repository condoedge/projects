<?php

namespace Condoedge\Projects\Kompo;

use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Kompo\Concerns\ReadOnlyWorkspace;
use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Projects\Models\Suggestion;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * A look at a record without leaving the list.
 *
 * Clicking a row used to be the only way to see what something actually said, and it cost the
 * page you were on — so scanning a list meant a round trip per row, and losing your filters and
 * your scroll each time. The drawer answers "what is this?" in place; its two buttons carry you
 * on when the answer is "I need to work on it".
 *
 * One component for the three record types rather than three near-identical ones: they differ in
 * which fields they show, not in what a preview is.
 *
 * On how much it shows: the first pass gave a suggestion one line — the requester's name — which
 * answered almost nothing. Everything the record already holds is here now: where it lives, who
 * raised it and how to reach them, when, and what became of it. A field with nothing in it is
 * left out rather than printed empty, so the panel stays the length of what is actually known.
 */
class PmRecordDrawer extends Form
{
    use PmElements;
    // Only for criteriaChecklist(), which takes its model as a plain argument. toggleCriterion()
    // is overridden below: the trait's own version reaches for $this->model, Kompo's single-model
    // binding — this component holds one of three record types in $this->record instead.
    use ReadOnlyWorkspace;

    public const ID = 'pm-record-drawer';
    public $id = self::ID;

    protected string $kind = 'feature';
    protected $record = null;

    public function created()
    {
        $this->kind = (string) ($this->prop('kind') ?? request('kind') ?? 'feature');

        $id = (int) ($this->prop('id') ?? request('id'));

        $this->record = match ($this->kind) {
            'suggestion' => Suggestion::asSystemOperation()
                ->with(['requestedByUser', 'addedBy', 'project', 'featureRequest'])->find($id),
            'task' => ProjectTask::asSystemOperation()
                ->with(['assignee', 'addedBy', 'project', 'featureRequest'])->find($id),
            default => FeatureRequest::asSystemOperation()
                ->withTaskProgress()->with(['addedBy', 'project'])->find($id),
        };
    }

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function render()
    {
        if (!$this->record) {
            return _Html('projects.record-gone')->class('text-graydark w-[25vw] min-w-[320px] p-7');
        }

        $r = $this->record;

        // Built as a plain array rather than inline spreads: textSections() returns a variable
        // number of elements, and PHP refuses a positional argument (the button row) after any
        // ...spread in the same call.
        $children = [
            $this->pmMarkdownStyles(),

            // pr-10 clears Kompo's own close button, which sits over the top-right corner.
            _Html($r->title)->class('text-lg font-bold text-level1 leading-snug pr-10'),

            _Flex(...$this->badges($r))->class('gap-3 items-center flex-wrap mt-4'),

            _Rows(...$this->details($r)),

            ...$this->textSections($r),

            $this->criteriaSection($r),

            // Where the drawer stops being enough.
            _Flex(
                _Link('projects.open-record')->icon('arrow-right')->button()
                    ->href($this->route(), ['id' => $r->id]),
                _Link('projects.edit')->icon(_SaxSvg('edit', 16))->button()->outlined()
                    ->href($this->route(), ['id' => $r->id, 'edit' => 1]),
            )->class('gap-3 mt-8'),
        ];

        // A quarter of the screen, not a fixed pixel width — min-w keeps it readable on a
        // narrow viewport, where 25vw alone would be too tight for the badges to stay on one row.
        return _Rows(...$children)->class('w-[25vw] min-w-[320px] p-7 max-h-screen overflow-y-auto');
    }

    protected function route(): string
    {
        return match ($this->kind) {
            'suggestion' => 'pm.suggestion',
            'task' => 'pm.task',
            default => 'pm.feature',
        };
    }

    protected function badges($r): array
    {
        return match ($this->kind) {
            'task' => [
                _Pill($r->status?->label())->class(($r->status?->color() ?: 'bg-graydark') . ' text-white'),
                $this->pmPriority($r->priority),
                $r->phase ? $this->pmPhase($r->phase) : null,
                !$r->kind ? null : _Html($r->kind->label())->class('text-sm text-graydark'),
            ],
            default => [
                _Pill($r->status?->label())->class(($r->status?->displayColor() ?: 'bg-graydark') . ' text-white'),
                $this->pmPriority($r->priority),
                $this->kind === 'feature' && $r->phase ? $this->pmPhase($r->phase) : null,
                $this->kind !== 'feature' || !$r->type ? null
                    : _Html($r->type->label())->class('text-sm text-graydark'),
            ],
        };
    }

    /**
     * What the record knows about itself, in three groups.
     *
     * Eight label-and-value lines in a row read as a wall: nothing said which of them answered
     * "where does this stand", which "what is it attached to", and which "where does it come
     * from". The groups always appear in that order, and one holding nothing is left out whole
     * rather than printed as an empty heading.
     */
    protected function details($r): array
    {
        return array_filter([
            $this->pmSection(__('projects.section-tracking'), $this->tracking($r)),
            $this->pmSection(__('projects.section-linked'), $this->linked($r)),
            $this->pmSection(__('projects.section-history'), $this->history($r)),
        ]);
    }

    /** Where it stands right now. */
    protected function tracking($r): array
    {
        return match ($this->kind) {
            'suggestion' => [
                $this->pmField('projects.reference', $r->reference),
                $this->requesterField($r),
                (int) $r->votes === 0 ? null : $this->pmField('projects.votes', (string) $r->votes),
            ],
            'task' => [
                $this->pmField('projects.assignee', $r->assignee?->name),
                $this->pmField('projects.due-date', $r->due_date?->translatedFormat('j M Y')),
                (int) $r->completion_pct === 0 ? null
                    : $this->pmField('projects.completion', $r->completion_pct . ' %'),
                $this->pmField('projects.criteria-short', $r->criteriaProgress() ?: null),
            ],
            default => [
                (int) ($r->tasks_count ?? 0) === 0 ? null
                    : $this->pmRow(__('projects.tasks'), $this->pmProgressOf($r)),
                !$r->effort_days ? null : $this->pmField('projects.effort-days', (string) $r->effort_days),
                !$r->complexity ? null : $this->pmField('projects.complexity', $r->complexity->label()),
                $this->kind !== 'feature' ? null
                    : $this->pmField('projects.criteria-short', $r->criteriaProgress() ?: null),
            ],
        };
    }

    /** What it hangs from. */
    protected function linked($r): array
    {
        $own = match ($this->kind) {
            'suggestion' => !$r->promoted_to_feature_request_id ? null : $this->linkField(
                'projects.promoted-to',
                $r->featureRequest?->title ?: '#' . $r->promoted_to_feature_request_id,
                'pm.feature',
                $r->promoted_to_feature_request_id
            ),
            'task' => !$r->feature_request_id ? null : $this->linkField(
                'projects.feature-request',
                $r->featureRequest?->title ?: '#' . $r->feature_request_id,
                'pm.feature',
                $r->feature_request_id
            ),
            default => null,
        };

        return [
            $own,
            $this->pmField('projects.project', $r->project?->name),
            $this->pmField('projects.team', $this->pmTeamName($r)),
        ];
    }

    /** Where it came from. */
    protected function history($r): array
    {
        return [
            $this->pmField('projects.created-at', $r->created_at?->translatedFormat('j M Y')),
            $this->pmField('projects.created-by', $r->addedBy?->name),
            $this->kind === 'suggestion' ? null : $this->githubField($r),
        ];
    }


    /**
     * The requester, with a way to reach them when there is one.
     *
     * Most records carry only a typed name: the link to a real user is optional and, in this
     * data, rarely filled. The email shows when the record has a user behind it and is left out
     * when it does not — printing an empty "Courriel" line would suggest the address is missing
     * rather than that nobody was linked.
     */
    protected function requesterField($r)
    {
        $user = $r->requestedByUser;
        $name = $r->requester_name ?: $user?->name;

        if (!$name && !$user) {
            return null;
        }

        return _Rows(
            $this->pmRow(__('projects.requester-name'), _Html($name ?: '—')->class('text-sm')),
            !$user?->email ? null : $this->pmRow(
                __('projects.email'),
                _Link($user->email)->class('text-sm text-level1 underline')
                    ->href('mailto:' . $user->email)
            ),
        )->class('gap-2');
    }

    protected function githubField($r)
    {
        if (!$r->github_issue_number) {
            return null;
        }

        [$owner, $repo] = $r->githubRepoTarget();

        return $this->pmRow(
            __('projects.github-issue'),
            _Link('#' . $r->github_issue_number)->class('text-sm text-level1 underline')
                ->href('https://github.com/' . $owner . '/' . $repo . '/issues/' . $r->github_issue_number)
                ->target('_blank')
        );
    }


    /**
     * Every long-text field the record holds, each under its own heading — a feature's problem,
     * proposed solution and menu notes used to arrive here as one untitled paragraph (whichever
     * mainText() picked), so the other two were simply missing from the drawer. Matches the split
     * FeatureWorkspacePage's own page draws them with.
     */
    protected function textSections($r): array
    {
        $fields = match ($this->kind) {
            'suggestion' => [['projects.description', $r->body]],
            'task' => [['projects.description', $r->description]],
            default => [
                ['projects.problem', $r->problem],
                ['projects.proposed-solution', $r->proposed_solution],
                ['projects.menu-design', $r->menu_design],
            ],
        };

        return array_values(array_filter(array_map(
            fn ($f) => $this->textSection($f[0], $f[1]),
            $fields
        )));
    }

    /** A heading, then its full text — no truncation: the drawer scrolls now instead. */
    protected function textSection(string $label, ?string $text)
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        return _Rows(
            _Html(__($label))->class('text-xs font-semibold uppercase tracking-wide text-graydark mb-2'),
            $this->pmMarkdown($text),
        )->class('mt-5 pt-4 border-t border-level5');
    }

    /** The checklist itself, not just its count — task and feature only, suggestions have none. */
    protected function criteriaSection($r)
    {
        if (!in_array($this->kind, ['task', 'feature'], true) || $r->criteriaItems()->isEmpty()) {
            return null;
        }

        return _Rows(
            _Html(trim(__('projects.acceptance-criteria') . ' ' . $r->criteriaProgress()))
                ->class('text-xs font-semibold uppercase tracking-wide text-graydark mb-2'),
            $this->criteriaChecklist($r, self::ID),
        )->class('mt-5 pt-4 border-t border-level5');
    }

    /** ReadOnlyWorkspace's own version reaches for $this->model; this component has no single
     * bound model, so it re-targets $this->record — already resolved fresh by created(). */
    public function toggleCriterion()
    {
        $this->record?->toggleCriterion((int) request('index'));
    }

    protected function linkField(string $label, string $text, string $route, $id)
    {
        return $this->pmRow(
            __($label),
            _Link($text)->class('text-sm text-level1 underline')->href($route, ['id' => $id])
        );
    }
}
