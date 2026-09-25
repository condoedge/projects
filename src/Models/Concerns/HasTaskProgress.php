<?php

namespace Condoedge\Projects\Models\Concerns;

use Condoedge\Projects\Models\Enums\TaskStatusEnum;

/**
 * How far the tasks under a record have got — for anything that owns tasks.
 *
 * Both a project and a feature request need the same two numbers, and the counting was written
 * out by hand at each call site. One shape for it means the definition of "done" lives in one
 * place: change what counts as finished and every list follows.
 */
trait HasTaskProgress
{
    /** Adds tasks_count and tasks_done_count to the query. */
    public function scopeWithTaskProgress($query)
    {
        return $query->withCount([
            'tasks',
            'tasks as tasks_done_count' => fn ($q) => $q->where('status', TaskStatusEnum::COMPLETED->value),
        ]);
    }

    /** Adds what needs a decision: blocked, and overdue without being closed. */
    public function scopeWithTaskAlerts($query)
    {
        return $query->withCount([
            'tasks as tasks_blocked_count' => fn ($q) => $q->where('status', TaskStatusEnum::BLOCKED->value),
            'tasks as tasks_late_count' => fn ($q) => $q
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now())
                ->whereNotIn('status', [TaskStatusEnum::COMPLETED->value, TaskStatusEnum::CANCELLED->value]),
        ]);
    }

    /** Reads the counts the scopes loaded, 0 when they were not. */
    public function taskProgress(): array
    {
        return [
            'done' => (int) ($this->tasks_done_count ?? 0),
            'total' => (int) ($this->tasks_count ?? 0),
        ];
    }
}
