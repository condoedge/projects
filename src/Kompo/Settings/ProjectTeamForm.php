<?php

namespace Condoedge\Projects\Kompo\Settings;

use Condoedge\Projects\Kompo\Concerns\SearchesUsers;
use Condoedge\Projects\Models\ProjectTeam;
use Condoedge\Utils\Kompo\Common\Modal;
use Kompo\Auth\Facades\UserModel;

class ProjectTeamForm extends Modal
{
    use SearchesUsers;

    public $_Title = 'projects.team';
    public $class = 'max-w-2xl';
    public $model = ProjectTeam::class;

    protected $teamId;

    // The base Modal adds its own "Sauvegarder" in the header, on top of the save button
    // this form already puts at the bottom. Two buttons for one submission.
    protected $noHeaderButtons = true;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->teamId = (int) $this->prop('team_id');

        if ($this->modelKey()) {
            $this->model(ProjectTeam::asSystemOperation()->findOrFail($this->modelKey()));
        }
    }

    public function beforeSave()
    {
        if (!$this->model->id) {
            $this->model->team_id = $this->teamId;
        }
    }

    public function body()
    {
        $existing = ($this->model instanceof ProjectTeam && $this->model->id) ? $this->model : null;

        return _Rows(
            _Input('projects.team-name')->name('name')->required()
                ->onEnter(fn ($e) => $e->closeModal()->refresh(ProjectTeamsTable::ID)),
            _Textarea('projects.description')->name('description')->rows(2),

            // Searched across SISC, like a task's assignee: whoever leads a team is not
            // necessarily one of the accounts attached to the team that owns it.
            _Select('projects.team-direction')->name('direction_user_id')
                ->searchOptions(2, 'searchUsers', 'retrieveUser'),

            // Members only once the team exists: each row belongs to it, so there has to be
            // something to belong to. Same order InvoiceForm imposes on its lines.
            !$existing ? _Html('projects.team-members-after-save')->class('text-sm text-gray-500 mt-4')
                : _Rows(
                    _Html('projects.team-members')->class('font-semibold mt-4 mb-2'),
                    // Kompo's own default ("Add a new item") never goes through the translator,
                    // so it stayed in English regardless of locale until overridden here.
                    _MultiForm()->noLabel()->addLabel(__('projects.add-member'))->name('members')
                        ->formClass(ProjectTeamMemberForm::class, [
                            'pm_team_id' => $existing->id,
                            'team_id' => $this->teamId,
                        ])
                        ->asTable([
                            _Th('projects.team-member'),
                            _Th('projects.list-team-role'),
                        ]),
                ),

            _FlexEnd(
                _SubmitButton('projects.save')->alert('projects.saved')
                    ->closeModal()->refresh(ProjectTeamsTable::ID),
            ),
        );
    }

    public function rules()
    {
        return [
            'name' => 'required|max:255',
        ];
    }
}
