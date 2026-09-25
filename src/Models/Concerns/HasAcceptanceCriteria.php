<?php

namespace Condoedge\Projects\Models\Concerns;

use Illuminate\Support\Collection;

/**
 * Acceptance criteria, each with a tick of its own.
 *
 * The column held bare strings, and imports could leave given/when/then triples in it. Both are
 * read here as {text, done} so nothing already stored has to be migrated — a criterion that has
 * never been ticked simply reads as not done.
 */
trait HasAcceptanceCriteria
{
    /** Normalised to {text, done}, whatever shape the column happens to hold. */
    public function criteriaItems(): Collection
    {
        return collect($this->acceptance_criteria ?: [])
            ->map(function ($criterion) {
                if (!is_array($criterion)) {
                    return ['text' => trim((string) $criterion), 'done' => false];
                }

                // Written by this module, or a given/when/then triple from an import.
                return isset($criterion['text'])
                    ? ['text' => trim((string) $criterion['text']), 'done' => (bool) ($criterion['done'] ?? false)]
                    : ['text' => implode(' / ', array_filter($criterion)), 'done' => false];
            })
            ->filter(fn ($criterion) => $criterion['text'] !== '')
            ->values();
    }

    /** What the textarea shows in edit mode: one line per criterion. */
    public function criteriaAsLines(): string
    {
        return $this->criteriaItems()->pluck('text')->implode("\n");
    }

    /** One line per criterion. Ticks already made are kept, matched on the text. */
    public function setCriteriaFromLines(?string $raw): void
    {
        $alreadyDone = $this->criteriaItems()->where('done', true)->pluck('text')->all();

        $this->acceptance_criteria = collect(preg_split('/\r\n|\r|\n/', (string) $raw))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(fn ($text) => ['text' => $text, 'done' => in_array($text, $alreadyDone, true)])
            ->values()
            ->all();
    }

    public function toggleCriterion(int $index): void
    {
        $items = $this->criteriaItems()->all();

        if (!isset($items[$index])) {
            return;
        }

        $items[$index]['done'] = !$items[$index]['done'];

        $this->acceptance_criteria = $items;
        $this->save();
    }

    /** "2 / 5", or an empty string when there is nothing to count. */
    public function criteriaProgress(): string
    {
        $items = $this->criteriaItems();

        return $items->isEmpty() ? '' : $items->where('done', true)->count() . ' / ' . $items->count();
    }
}
