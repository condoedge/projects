<?php

namespace Condoedge\Projects\Kompo\Settings;

use Condoedge\Projects\Kompo\Concerns\SearchesUsers;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\ProjectTeam;
use Condoedge\Projects\Models\ProjectTeamMember;
use Condoedge\Utils\Kompo\Common\Form;
use Kompo\Auth\Facades\UserModel;

/**
 * One row of the members table inside ProjectTeamForm — a person and the post they hold.
 * Rendered through _MultiForm, the way InvoiceDetailForm is rendered inside InvoiceForm.
 */
class ProjectTeamMemberForm extends Form
{
    use SearchesUsers;

    public $model = ProjectTeamMember::class;

    protected $projectTeam;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->projectTeam = ProjectTeam::asSystemOperation()->find($this->prop('pm_team_id'));
    }

    public function beforeSave()
    {
        if (!$this->model->id) {
            $this->model->pm_team_id = $this->prop('pm_team_id');
        }

        // The prepared list used to make a double impossible by leaving taken accounts out.
        // Searching the whole of SISC removes that guard, so it is enforced here instead.
        $duplicate = ProjectTeamMember::where('pm_team_id', $this->model->pm_team_id)
            ->where('user_id', $this->model->user_id)
            ->when($this->model->id, fn ($q) => $q->where('id', '!=', $this->model->id))
            ->exists();

        if ($duplicate) {
            abort(422, __('projects.team-member-already-added'));
        }
    }

    public function render()
    {
        return _Columns(
            // Searched across SISC rather than picked from a prepared list: the accounts attached
            // to one team are far too few to build a team from — the test team has exactly one.
            // Same idiom as the task assignee in TaskForm.
            _Select()->name('user_id')->required()
                ->searchOptions(2, 'searchUsers', 'retrieveUser'),

            _Select()->name('role_value')
                ->options(ListValue::optionsFor(ListValue::TEAM_ROLE, (int) $this->prop('team_id'))),
        );
    }

    public function rules()
    {
        return [
            'user_id' => 'required',
        ];
    }
}
