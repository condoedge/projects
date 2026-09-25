<?php

namespace Condoedge\Projects\Kompo\Board;

use Condoedge\Projects\Kompo\Board\Concerns\BoardHeader;
use Condoedge\Projects\Models\ProjectTask;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Utils\Kompo\Common\Form;
use Illuminate\Support\Carbon;

class GanttPage extends Form
{
    use BoardHeader;
    use PmElements;

    public $containerClass = 'fullContainer';

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function render()
    {
        $projectId = request('project_id');

        $tasks = ProjectTask::asSystemOperation()
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->with('project:id,name')
            ->get();

        // Timeline window from the dated tasks (padded a few days).
        $dates = $tasks->flatMap(fn ($t) => array_filter([$t->start_date, $t->due_date]));
        $min = $dates->min();
        $max = $dates->max();
        $window = null;
        if ($min && $max) {
            $start = $min->copy()->subDays(2)->startOfDay();
            $end = $max->copy()->addDays(2)->startOfDay();
            $span = max(1, $start->diffInDays($end));
            $today = Carbon::today();
            $window = [
                'start' => $start,
                'end' => $end,
                'span' => $span,
                'todayPct' => ($today->betweenIncluded($start, $end)) ? round($start->diffInDays($today) / $span * 100, 2) : null,
                'label' => $start->isoFormat('D MMM YY').' → '.$end->isoFormat('D MMM YY'),
            ];
        }

        // Rows grouped by project, each dated task gets left/width %.
        $groups = $tasks->groupBy(fn ($t) => $t->project?->name ?? '—')->map(function ($grp) use ($window) {
            return $grp->sortBy(fn ($t) => optional($t->start_date ?? $t->due_date)->timestamp)->map(function ($t) use ($window) {
                $bar = null;
                if ($window) {
                    $s = $t->start_date ?? $t->due_date;
                    $e = $t->due_date ?? $t->start_date;
                    if ($s && $e) {
                        $left = $window['start']->diffInDays($s) / $window['span'] * 100;
                        $width = max(1.5, $window['start']->diffInDays($e) / $window['span'] * 100 - $left);
                        $bar = ['left' => round($left, 2), 'width' => round($width, 2)];
                    }
                }

                return ['task' => $t, 'bar' => $bar];
            });
        });

        return _Rows(
            $this->boardHeader($projectId, 'pm.gantt'),
            _Html(view('projects::gantt', ['groups' => $groups, 'window' => $window])->render()),
        );
    }
}
