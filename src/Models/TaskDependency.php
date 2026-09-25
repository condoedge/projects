<?php

namespace Condoedge\Projects\Models;

use Condoedge\Projects\Models\Enums\DependencyTypeEnum;
use Condoedge\Utils\Models\Model;

class TaskDependency extends Model
{
    protected $table = 'pm_task_dependencies';
    protected $guarded = [];

    protected $casts = [
        'type' => DependencyTypeEnum::class,
    ];

    public function predecessor()
    {
        return $this->belongsTo(ProjectTask::class, 'predecessor_task_id');
    }

    public function successor()
    {
        return $this->belongsTo(ProjectTask::class, 'successor_task_id');
    }
}
