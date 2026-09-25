<?php

namespace Condoedge\Projects\Kompo\Concerns;

use Condoedge\Projects\Models\ListValue;

/**
 * The three pieces every list and page in the module repeats: the status you can change from
 * where you stand, the priority, and how far something has got.
 *
 * They live in one trait because the module had written them three ways — a pill here, a
 * modal there, a raw count elsewhere — and the inconsistency was doing the reading.
 *
 * Statuses and priorities arrive as ListValue (a team may reshape the list), so everything
 * goes through the ref's label()/displayColor() and reaches the enum only for what a team cannot
 * change: the text colour of a priority, and whether it is the critical one.
 */
trait PmElements
{
    /**
     * A status you can change from where you stand: the pill IS the control.
     *
     * It replaces ⋮ → "change status" → modal → select → save → refresh — five gestures for a
     * single field, on the action a project tool performs more than any other. The component
     * using this declares setPmStatus($id, $value) and says what to redraw.
     */
    protected function pmStatusPill($status, $id, string $listKey, $teamId, string $refreshId)
    {
        // The cast hands back a ListValueRef — label()/displayColor()/->value — not the
        // ListValue row itself; only the options below are real rows.
        $label = $status?->label() ?: __('projects.no-status');
        $colour = $status?->displayColor() ?: 'bg-graydark';
        $current = $status?->value;

        return _Dropdown($label)
            // asPill() carries the app's pill geometry; only the colour and the fact that this
            // one is a trigger are ours to add.
            ->asPill($colour . ' text-white')
            ->class('whitespace-nowrap cursor-pointer w-min inline-flex items-center gap-1')
            ->icon(_Sax('arrow-down-1', 12))
            ->submenu(
                ListValue::forList($listKey, (int) $teamId)->map(
                    fn ($option) => _DropdownLink($option->displayName())
                        ->when(
                            $option->getAttributeValue('value') == $current,
                            fn ($el) => $el->icon(_Sax('tick-circle', 14))
                        )
                        ->selfPost('setPmStatus', [
                            'id' => $id,
                            'value' => $option->getAttributeValue('value'),
                        ])
                        ->refresh($refreshId)
                )->all()
            )
            ->alignRight();
    }

    /**
     * The same control for a status that is a plain enum rather than a team-shaped list.
     *
     * Task statuses ARE the Kanban's columns, so a team cannot reshape them — they never became
     * ListValue entries and need their own picker.
     */
    protected function pmEnumStatusPill($status, $id, string $enumClass, string $refreshId)
    {
        return _Dropdown($status?->label() ?: __('projects.no-status'))
            ->asPill(($status?->color() ?: 'bg-graydark') . ' text-white')
            ->class('whitespace-nowrap cursor-pointer w-min inline-flex items-center gap-1')
            ->icon(_Sax('arrow-down-1', 12))
            ->submenu(
                collect($enumClass::cases())->map(
                    fn ($case) => _DropdownLink($case->label())
                        ->when($case === $status, fn ($el) => $el->icon(_Sax('tick-circle', 14)))
                        ->selfPost('setPmStatus', ['id' => $id, 'value' => $case->value])
                        ->refresh($refreshId)
                )->all()
            )
            ->alignRight();
    }

    /**
     * A due date that only shouts when it should.
     *
     * Every date on the board was red, overdue or not, so red had stopped meaning anything.
     */
    protected function pmDueDate($date, $isClosed = false)
    {
        if (!$date) {
            return _Html('—')->class('text-sm text-graydark');
        }

        $overdue = !$isClosed && $date->isPast();

        return _Html($date->translatedFormat('j M Y'))
            ->class($overdue ? 'text-sm font-semibold text-dangerdark' : 'text-sm text-graydark');
    }

    /** The four ways of looking at the same work, in one fixed order. */
    public const VIEW_TABS = 'tabs';
    public const VIEW_PIPELINE = 'pipeline';
    public const VIEW_BOARD = 'board';
    public const VIEW_GANTT = 'gantt';

    /**
     * The header every page of the module wears.
     *
     * Five pages had grown five different sets of buttons, and the board and the Gantt each
     * dropped the view you were already on — so walking between them made controls vanish and
     * reappear, and you could never learn where anything lived. The set is fixed now; the view
     * you are on is marked rather than removed, which is also what tells you where you are.
     *
     * Tabs need a project, so that one stands down on the views that open across all of them.
     */
    protected function pmHeader($title, string $current, $projectId = null, $subtitle = null)
    {
        return _Rows(
            _Flex(
                // Back goes up one level: to the project from a view of it, to the list from the
                // project itself. Sending the tabs page "back to the project" pointed it at
                // itself, which is a dead end wearing an arrow.
                ($projectId && $current !== self::VIEW_TABS)
                    ? _Link('projects.back-to-project')->icon('arrow-left')->class('text-graydark')
                        ->href('pm.project-board', ['project_id' => $projectId])
                    : _Link('projects.projects')->icon('arrow-left')->class('text-graydark')
                        ->href('pm.projects'),

                _Rows(
                    _Html($title)->class('text-2xl font-bold text-level1'),
                    !$subtitle ? null : _Html($subtitle)->class('text-sm text-graydark mt-1'),
                )->class('flex-auto'),

                $this->pmViewSwitcher($current, $projectId),
            )->class('items-center gap-4 mb-4'),
        );
    }

    /** The switcher alone, for the one page that has no back link. */
    protected function pmViewSwitcher(string $current, $projectId = null)
    {
        $params = $projectId ? ['project_id' => $projectId] : [];

        $views = array_filter([
            !$projectId ? null : [self::VIEW_TABS, 'projects.board-tabs', 'pm.project-board', $params],
            [self::VIEW_PIPELINE, 'projects.pipeline', 'pm.pipeline', $params],
            [self::VIEW_BOARD, 'projects.board', 'pm.board', $params],
            [self::VIEW_GANTT, 'projects.gantt', 'pm.gantt', $params],
        ]);

        return _Flex(
            ...collect($views)->map(function ($v) use ($current) {
                [$key, $label, $route, $params] = $v;
                $on = $key === $current;

                return _Link($label)
                    ->class('text-sm px-4 py-2 rounded-lg whitespace-nowrap '
                        . ($on
                            ? 'bg-white text-level1 font-semibold shadow-sm'
                            : 'text-graydark hover:text-level1'))
                    ->href($route, $params);
            })->all()
        )->class('bg-level5 rounded-xl p-1 gap-1 shrink-0');
    }

    /**
     * Long text rendered as the Markdown it actually is.
     *
     * The fields hold Markdown — bullet lists, numbered lists, rules — and were printed raw with
     * whitespace-pre-line, so a reader saw the dashes and the "---" instead of a list and a
     * separator. html_input strip and allow_unsafe_links false: the content is written by admins,
     * but text rendered as html is text that can carry html, and nothing here needs to.
     */
    protected function pmMarkdown($text)
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $html = \Illuminate\Support\Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return _Html('<div class="pm-md">' . $html . '</div>');
    }

    /**
     * The list and spacing rules Tailwind's reset strips out, emitted once per page.
     *
     * A <style> inside v-html does apply — unlike a <script> — which is what lets the package
     * carry its own rules without reaching into the app's stylesheet.
     */
    protected function pmMarkdownStyles()
    {
        return _Html(
            '<style>'
            . '.pm-md{font-size:.875rem;line-height:1.6;color:#16231D}'
            . '.pm-md p{margin:0 0 .6em}'
            . '.pm-md p:last-child{margin-bottom:0}'
            . '.pm-md ul{list-style:disc;padding-left:1.15rem;margin:0 0 .6em}'
            . '.pm-md ol{list-style:decimal;padding-left:1.35rem;margin:0 0 .6em}'
            . '.pm-md li{margin:.15em 0}'
            . '.pm-md li>ul,.pm-md li>ol{margin:.2em 0}'
            . '.pm-md hr{border:0;border-top:1px solid #cde4dd;margin:.9em 0}'
            . '.pm-md h1,.pm-md h2,.pm-md h3{font-weight:700;color:#006241;margin:.9em 0 .35em;font-size:1em}'
            . '.pm-md code{background:#EBEBEB;border-radius:3px;padding:.05em .3em;font-size:.9em}'
            . '.pm-md pre{background:#F4F7F5;border-radius:6px;padding:.6em .8em;overflow-x:auto;margin:0 0 .6em}'
            . '.pm-md pre code{background:none;padding:0}'
            . '.pm-md a{color:#006241;text-decoration:underline}'
            . '.pm-md blockquote{border-left:3px solid #cde4dd;padding-left:.8em;color:#5F5F5F;margin:0 0 .6em}'
            . '.pm-md table{border-collapse:collapse;margin:0 0 .6em}'
            . '.pm-md th,.pm-md td{border:1px solid #cde4dd;padding:.25em .5em;text-align:left}'
            . '</style>'
        );
    }

    /**
     * One label-and-value line, so every field in the module lines up the same way.
     *
     * These live here rather than in the drawer because the record pages need exactly the same
     * blocks: the drawer had grown a context the pages did not show, and two renderings of the
     * same facts drift apart the first time one of them is edited.
     */
    protected function pmRow(string $label, $value)
    {
        return _Flex(
            _Html($label)->class('text-sm text-graydark w-36 shrink-0'),
            _Rows($value)->class('flex-auto min-w-0'),
        )->class('!items-start gap-3');
    }

    protected function pmField(string $label, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->pmRow(__($label), _Html((string) $value)->class('text-sm'));
    }

    /** A titled block, or nothing at all when it would hold nothing. */
    protected function pmSection(string $title, array $fields)
    {
        $fields = array_filter($fields);

        if (!$fields) {
            return null;
        }

        return _Rows(
            _Html($title)->class('text-xs font-semibold uppercase tracking-wide text-graydark mb-2'),
            _Rows(...$fields)->class('gap-2'),
        )->class('mt-5 pt-4 border-t border-level5');
    }

    /** The team a record belongs to, by id, since none of the models relate to it. */
    protected function pmTeamName($record): ?string
    {
        if (!$record->team_id) {
            return null;
        }

        $team = \Kompo\Auth\Models\Teams\Team::find($record->team_id);

        return $team?->team_name ?? $team?->name;
    }

    /**
     * Where a record lives and where it came from — the same two blocks everywhere.
     *
     * The drawer answered "which project, which team, since when, raised by whom" while the
     * record's own page did not, so opening the full page lost context instead of adding it.
     */
    protected function pmContextSections($record)
    {
        return _Rows(
            $this->pmSection(__('projects.section-linked'), [
                $this->pmField('projects.project', $record->project?->name),
                $this->pmField('projects.team', $this->pmTeamName($record)),
            ]),
            $this->pmSection(__('projects.section-history'), [
                $this->pmField('projects.created-at', $record->created_at?->translatedFormat('j M Y')),
                $this->pmField('projects.created-by', $record->addedBy?->name),
            ]),
        );
    }

    /**
     * The lifecycle as a row of stages you can click.
     *
     * It was a Blade picture of the pipeline with the way through it sitting in a separate
     * control below — you read the stage in one place and changed it in another. Built from
     * Kompo elements rather than raw markup precisely so each stage can carry an interaction;
     * raw markup inside _Html cannot.
     */
    protected function pmStepper($current, $id, array $cases, string $refreshId)
    {
        $values = array_map(fn ($c) => $c->value, $cases);
        $currentIdx = $current ? array_search($current->value, $values, true) : 0;
        $last = count($cases) - 1;

        return _Flex(
            ...collect($cases)->flatMap(function ($case, $i) use ($currentIdx, $id, $refreshId, $last) {
                $done = $i < $currentIdx;
                $isCurrent = $i === $currentIdx;
                $hex = method_exists($case, 'hex') ? $case->hex() : '#006241';

                $bg = $isCurrent ? $hex : ($done ? '#009243' : '#EBEBEB');
                $fg = ($isCurrent || $done) ? '#fff' : '#5F5F5F';

                // The disc is raw markup: a class-and-attr Kompo element rendered as bare text
                // here, with neither its size nor its background surviving.
                $step = _Rows(
                    _Html(
                        '<div style="width:28px;height:28px;border-radius:50%;background:' . $bg
                        . ';color:' . $fg . ';display:flex;align-items:center;justify-content:center;'
                        . 'font-size:12px;font-weight:700">' . ($done ? '&check;' : ($i + 1)) . '</div>'
                    ),
                    _Html($case->label())->class('text-xs text-center mt-1 '
                        . ($isCurrent ? 'text-level1 font-semibold' : 'text-graydark')),
                )
                    ->class('items-center w-24 cursor-pointer')
                    ->selfPost('setPmStatus', ['id' => $id, 'value' => $case->value])
                    ->refresh($refreshId);

                return array_filter([
                    $step,
                    $i === $last ? null : _Html(
                        '<div style="height:2px;width:24px;background:'
                        . ($i < $currentIdx ? '#009243' : '#EBEBEB') . ';margin-bottom:26px"></div>'
                    ),
                ]);
            })->all()
        )->class('items-center flex-wrap');
    }

    /**
     * Priority as a bar and a word, never a second filled pill.
     *
     * Two filled pills side by side — amber status, amber priority — had the same shape, the
     * same weight and often the same colour, so the row said one thing twice and neither
     * clearly. Giving priority its own form frees the pill for the stage alone.
     */
    protected function pmPriority($priority)
    {
        if (!$priority) {
            return _Html('—')->class('text-sm text-graydark');
        }

        // Priority is not one type across the module: a feature request and a task carry a
        // ListValueRef, a suggestion carries a plain 0-3 integer with no cast at all. Handing the
        // integer to method_exists() threw, and it threw inside the drawer — so opening any
        // suggestion preview failed with a type error rather than showing the record.
        if (!is_object($priority)) {
            return $this->pmStars((int) $priority);
        }

        $case = method_exists($priority, 'entry') ? $priority->entry()?->enumCase() : null;
        $textColour = $case && method_exists($case, 'textColor') ? $case->textColor() : 'text-graydark';

        // The top of the scale earns a filled shape: there, the loudness is the message.
        if ($case && method_exists($case, 'isCritical') && $case->isCritical()) {
            return _Pill($priority->label())->class('bg-danger text-white whitespace-nowrap');
        }

        return _Flex(
            _Html('')->class(($priority->displayColor() ?: 'bg-graydark') . ' w-1 h-4 rounded-sm shrink-0'),
            _Html($priority->label())->class($textColour . ' text-sm font-semibold whitespace-nowrap'),
        )->class('items-center gap-2');
    }

    /** The 1-3 scale a suggestion uses, drawn the way its own list draws it. */
    protected function pmStars(int $level)
    {
        if ($level < 1) {
            return _Html('—')->class('text-sm text-graydark');
        }

        return _Flex(
            ...collect(range(1, 3))->map(fn ($i) => _Html('★')
                ->class($i <= $level ? 'text-warning' : 'text-gray-300'))->all()
        )->class('gap-1 text-sm');
    }

    /**
     * How far something has got, in place of a raw count.
     *
     * "6 tasks" never said whether six were waiting or five were finished: the number was
     * there, the state was not.
     */
    protected function pmProgress(int $done, int $total, ?string $noneLabel = null)
    {
        if ($total === 0) {
            return _Html($noneLabel ?: '—')->class('text-sm text-graydark');
        }

        return _Rows(
            _ProgressBar($done / $total, 'bg-positive'),
            _Html($done . ' / ' . $total)->class('text-xs text-graydark mt-1 whitespace-nowrap'),
        )->class('w-full min-w-[80px]');
    }

    /** The same, read straight off a record that carries the counts. */
    protected function pmProgressOf($model, ?string $noneLabel = null)
    {
        ['done' => $done, 'total' => $total] = $model->taskProgress();

        return $this->pmProgress($done, $total, $noneLabel);
    }

    /** The same bar without the caption, for a card that already says the numbers. */
    protected function pmProgressBar(int $done, int $total)
    {
        return _ProgressBar($total === 0 ? 0 : $done / $total, 'bg-positive');
    }
}
