<?php

namespace Condoedge\Projects\Kompo\Board\Concerns;

use Condoedge\Projects\Models\Project;

/**
 * The header Kanban and Gantt share — now the same one every other page of the module wears.
 *
 * It used to build its own pair of buttons and drop whichever view you were already on, so the
 * controls changed shape as you moved between views and neither said where you stood. It defers
 * to pmHeader() now; the only thing left here is turning a project id into a title.
 */
trait BoardHeader
{
    protected function boardHeader($projectId, string $currentRoute)
    {
        $project = $projectId ? Project::asSystemOperation()->find($projectId) : null;

        $view = match ($currentRoute) {
            'pm.gantt' => static::VIEW_GANTT,
            'pm.phases' => static::VIEW_PHASES,
            default => static::VIEW_BOARD,
        };

        return $this->pmHeader($project?->name ?: __('projects.all-projects'), $view, $project?->id, $project?->code);
    }
}
