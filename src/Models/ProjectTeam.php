<?php

namespace Condoedge\Projects\Models;

use Condoedge\Utils\Models\Model;
use Kompo\Auth\Contracts\Security\ScopedToTeam;
use Kompo\Auth\Facades\UserModel;
use Kompo\Auth\Models\Concerns\Security\BelongsToOneTeam;
use Kompo\Auth\Models\Teams\BelongsToTeamTrait;

/**
 * A working team: a named group of people inside the SISC team that owns it. Not to be confused
 * with that owner — this one has no tenancy, no permissions, no hierarchy.
 */
class ProjectTeam extends Model implements ScopedToTeam
{
    use BelongsToOneTeam, BelongsToTeamTrait;

    protected $table = 'pm_teams';
    protected $guarded = [];

    public function members()
    {
        return $this->hasMany(ProjectTeamMember::class, 'pm_team_id');
    }

    /** Whoever leads the team — an account anywhere in SISC, not only in the owning team. */
    public function direction()
    {
        return $this->belongsTo(UserModel::getClass(), 'direction_user_id');
    }
}
