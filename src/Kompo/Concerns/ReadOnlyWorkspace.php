<?php

namespace Condoedge\Projects\Kompo\Concerns;

/**
 * The shape every detail page in the module shares: read-only by default, editable behind the
 * Modifier button, laid out the way App\Kompo\Events\Activity\ActivityPage lays out a record.
 *
 * Edit mode is the same route with ?edit=1 rather than a second page, so the two views cannot
 * drift apart and a link to a record always lands somewhere readable.
 */
trait ReadOnlyWorkspace
{
    protected bool $editing = false;

    /**
     * The flag has to survive the ajax round trip. A field submits to /_kompo without the query
     * string, so reading `edit` from the request alone rebuilt the page read-only — leaving no
     * field to fill, and every edit was dropped without a word. The store travels with the
     * component; the query string only comes with the first page load.
     */
    protected function readEditMode(): void
    {
        $this->editing = (bool) (request('edit') ?: $this->prop('edit'));

        if ($this->editing) {
            $this->store(['edit' => true]);
        }
    }

    /** Back link, title, and the button that flips between the two modes. */
    protected function workspaceHeader(string $route, $id, string $backRoute, array $backParams, string $titleField = 'title')
    {
        return _Rows(
            // !w-auto undoes the helper's own w-min, which otherwise breaks the label onto
            // one word per line.
            _BackButton($backRoute, $backParams, 'projects.back-to-project')
                ->class('mb-4 !w-auto whitespace-nowrap'),

            _FlexBetween(
                $this->editing
                    ? $this->auto(_Input()->name($titleField))->class('text-level1 text-2xl font-bold')
                    : _Html($this->model->{$titleField})->class('text-level1 text-2xl font-bold'),

                // Terminer submits before it navigates. Fields commit when they lose focus, so a
                // plain link left whatever was just typed behind: the click navigated away before
                // the blur ever fired, and the last field edited was silently dropped.
                $this->editing
                    ? _Button('projects.done-editing')->icon('check')
                        ->onClick(fn ($e) => $e->submit()->redirect($route, ['id' => $id]))
                    // Tried and reverted: flipping to edit with selfPost + refresh instead of
                    // navigating. store() set inside the handler does not survive into the
                    // redraw, so the page came back read-only and the button did nothing.
                    // The query flag is what actually carries the mode here.
                    : _Link('projects.edit')->icon(_SaxSvg('edit', 16))->button()
                        ->href($route, ['id' => $id, 'edit' => 1]),
            )->class('border-b-2 border-level1 pb-2'),
        );
    }

    /** The two-column split: content on the left, a 350px rail on the right. */
    protected function workspaceColumns(array $main, array $side)
    {
        return _Rows(
            $this->pmMarkdownStyles(),
            _Flex(
                _Rows(...$main)->class('flex-1 min-w-0 pt-4 gap-4'),
                // 350px left the side rail cramped: its labels wrapped and its values were
                // squeezed while the main column had room to spare.
                _Rows(...$side)->class('w-[430px] shrink-0 pt-4 gap-4'),
            )->class('!items-start gap-6'),
        );
    }

    /** The SISC card: a level1 heading over a rule, white body. */
    protected function infoBlock($title, ...$els)
    {
        return _CardWhite(
            _Html($title)->class('text-lg text-level1 border-b border-gray-600/30 mb-3 pb-1'),
            ...$els,
        )->p4();
    }

    protected function detailRow($label, $value)
    {
        return _Flex(
            _Html($label)->class('text-sm text-gray-500 w-32 shrink-0'),
            is_string($value) || is_null($value) || is_numeric($value)
                ? $this->readText($value)
                : $value,
        )->class('gap-2 items-center py-1');
    }

    /** A value, or a muted dash when there is none — never an empty space. */
    protected function readText($value)
    {
        $filled = $value !== null && $value !== '';

        if (!$filled) {
            return _Html('—')->class('text-gray-400');
        }

        // These fields are written in Markdown; printing them raw showed the dashes and the
        // rules instead of the lists and separators they stand for.
        return $this->pmMarkdown($value);
    }

    /**
     * Acceptance criteria as a tickable list. Each box posts its own index rather than the whole
     * list, so two people ticking different lines cannot overwrite one another.
     */
    protected function criteriaChecklist($model, string $refreshId)
    {
        $items = $model->criteriaItems();

        if ($items->isEmpty()) {
            return $this->readText(null);
        }

        return _Rows(
            $items->map(fn ($criterion, $index) => _Flex(
                // mb-0 drops the default bottom margin Kompo gives a form control: it made the
                // checkbox's wrapper taller than the box, so centring the row put the tick above
                // its own label.
                _Checkbox()->name('crit_' . $index, false)->default($criterion['done'])
                    ->class('mb-0')
                    ->onChange(fn ($e) => $e->selfPost('toggleCriterion', ['index' => $index])
                        ->refresh($refreshId)),
                _Html($criterion['text'])->class($criterion['done']
                    ? 'line-through text-gray-400'
                    : 'whitespace-pre-wrap'),
            )->class('gap-2 !items-center py-1'))->all()
        );
    }

    /** Called by the checkboxes above. */
    public function toggleCriterion()
    {
        $this->model->toggleCriterion((int) request('index'));
    }

    // Each field commits when it loses focus, so edit mode needs no save button either.
    protected function auto($el)
    {
        return $el->onBlur(fn ($e) => $e->submit());
    }

    protected function autoSel($el)
    {
        return $el->onChange(fn ($e) => $e->submit());
    }
}
