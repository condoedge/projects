<?php

namespace Condoedge\Projects\Models;

use Condoedge\Projects\Models\Casts\ListValueCast;
use Condoedge\Utils\Models\Model;
use Kompo\Auth\Facades\UserModel;

class ProjectTeamMember extends Model
{
    protected $table = 'pm_team_members';
    protected $guarded = [];

    protected $casts = [
        'role_value' => ListValueCast::class . ':' . ListValue::TEAM_ROLE,
    ];

    public function projectTeam()
    {
        return $this->belongsTo(ProjectTeam::class, 'pm_team_id');
    }

    public function user()
    {
        return $this->belongsTo(UserModel::getClass(), 'user_id');
    }

    /**
     * The cast reads team_id off the row to resolve its list entry, but a member row has no
     * team_id of its own — it borrows the one from the team it belongs to.
     */
    public function getTeamIdAttribute()
    {
        return $this->projectTeam?->team_id;
    }
}
