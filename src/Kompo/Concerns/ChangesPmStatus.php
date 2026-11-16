<?php

namespace Condoedge\Projects\Kompo\Concerns;

/**
 * The handler behind the status pill, written once.
 *
 * Five components draw a pill, and each had grown its own copy of the same three lines with only
 * the model class differing — the kind of duplication that drifts silently the first time one of
 * them needs a rule the others do not get.
 *
 * A component that draws a pill says which record it acts on; everything else is here. Override
 * applyPmStatus() where saving a status means more than writing the column — a task that becomes
 * complete also owes its completion percentage.
 */
trait ChangesPmStatus
{
    /** The record the pill acts on. */
    abstract protected function pmStatusModel($id);

    public function setPmStatus($id, $value = null)
    {
        $model = $this->pmStatusModel($id);
        $value = (int) (request('value') ?? $value);

        if (!$model || !$value) {
            return;
        }

        $this->applyPmStatus($model, $value);
    }

    /** Writing the column is the whole job unless a model says otherwise. */
    protected function applyPmStatus($model, int $value): void
    {
        $model->status = $value;
        $model->save();
    }

    /**
     * The same one-line write behind the priority and phase pills.
     *
     * Both are plain ListValue columns — no completion-percentage rule rides along as it does for
     * status — so a single setter handles either, told apart by the column it writes.
     */
    public function setPmPriority($id, $value = null)
    {
        $this->writePmListColumn($id, 'priority', request('value') ?? $value);
    }

    public function setPmPhase($id, $value = null)
    {
        $this->writePmListColumn($id, 'phase', request('value') ?? $value);
    }

    public function setPmType($id, $value = null)
    {
        $this->writePmListColumn($id, 'type', request('value') ?? $value);
    }

    protected function writePmListColumn($id, string $column, $value): void
    {
        $model = $this->pmStatusModel($id);

        if (!$model) {
            return;
        }

        $model->{$column} = ($value === null || $value === '') ? null : (int) $value;
        $model->save();
    }
}
