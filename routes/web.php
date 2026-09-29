<?php

use Condoedge\Projects\Http\Controllers\TaskStatusController;
use Condoedge\Projects\Kompo\Board\GanttPage;
use Condoedge\Projects\Kompo\Board\KanbanPage;
use Condoedge\Projects\Kompo\Board\PhaseBoardPage;
use Condoedge\Projects\Kompo\FeatureRequests\FeatureWorkspacePage;
use Condoedge\Projects\Kompo\Projects\ProjectBoardPage;
use Condoedge\Projects\Kompo\PmRecordDrawer;
use Condoedge\Projects\Kompo\Projects\PipelinePage;
use Condoedge\Projects\Kompo\Projects\ProjectsPage;
use Condoedge\Projects\Kompo\Suggestions\SuggestionWorkspacePage;
use Condoedge\Projects\Kompo\Suggestions\TriagePage;
use Condoedge\Projects\Kompo\Tasks\TaskWorkspacePage;
use Illuminate\Support\Facades\Route;

// Phase 1: super_admin only (same gate as the app's admin block).
Route::layout('layouts.dashboard')->middleware(['superadmin'])->group(function () {
    Route::get('admin/projects', ProjectsPage::class)->name('pm.projects');
    Route::get('admin/projects/{project_id}/board', ProjectBoardPage::class)->name('pm.project-board');
    Route::get('admin/projects-pipeline', PipelinePage::class)->name('pm.pipeline');
    Route::get('admin/projects-triage', TriagePage::class)->name('pm.triage');
    Route::get('admin/projects/feature/{id}', FeatureWorkspacePage::class)->name('pm.feature');
    Route::get('admin/projects/task/{id}', TaskWorkspacePage::class)->name('pm.task');
    Route::get('admin/projects/suggestion/{id}', SuggestionWorkspacePage::class)->name('pm.suggestion');
    Route::get('admin/projects-kanban', KanbanPage::class)->name('pm.board');
    Route::get('admin/projects-gantt', GanttPage::class)->name('pm.gantt');
    Route::get('admin/projects-phases', PhaseBoardPage::class)->name('pm.phases');
});

// The drawer's own route sits OUTSIDE the layout group on purpose: a drawer is filled with the
// component's html alone, and Route::layout() would wrap it in the whole dashboard page.
Route::middleware(['web', 'superadmin'])
    ->get('admin/projects-record/{kind}/{id}', PmRecordDrawer::class)->name('pm.record-drawer');

// Kanban drag-drop persistence (session-authenticated, super_admin only).
Route::middleware(['web', 'superadmin'])->post('admin/projects-task-status', [TaskStatusController::class, 'update'])->name('pm.task-status');
