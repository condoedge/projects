<?php

namespace Condoedge\Projects\Http\Controllers;

use Condoedge\Projects\Models\Enums\TaskStatusEnum;
use Condoedge\Projects\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Persists a Kanban drag-drop: sets a task's status (super_admin only). */
class TaskStatusController extends Controller
{
    public function update(Request $request)
    {
        abort_unless(isSuperAdmin(), 403);

        $status = TaskStatusEnum::tryFrom((int) $request->get('status'));
        abort_unless($status, 422);

        $task = ProjectTask::asSystemOperation()->findOrFail($request->get('task_id'));
        $task->status = $status;
        if ($status === TaskStatusEnum::COMPLETED) {
            $task->completion_pct = 100;
        }
        $task->save();

        return response()->json(['ok' => true]);
    }
}
