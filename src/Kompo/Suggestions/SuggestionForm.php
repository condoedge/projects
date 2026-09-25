<?php

namespace Condoedge\Projects\Kompo\Suggestions;

use Condoedge\Projects\Kompo\Concerns\SearchesUsers;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Kompo\Suggestions\SuggestionsTable;
use Condoedge\Projects\Models\Enums\SuggestionStatusEnum;
use Condoedge\Projects\Models\Suggestion;
use Condoedge\Utils\Kompo\Common\Modal;
use Kompo\Auth\Facades\UserModel;

class SuggestionForm extends Modal
{
    use SearchesUsers;

    public $_Title = 'projects.new-suggestion';
    public $model = Suggestion::class;

    // The base Modal adds its own "Sauvegarder" in the header, on top of the save button
    // this form already puts at the bottom. Two buttons for one submission.
    protected $noHeaderButtons = true;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        if ($this->modelKey()) {
            $this->model(Suggestion::asSystemOperation()->findOrFail($this->modelKey()));
        }
    }

    public function beforeSave()
    {
        if (!$this->model->id) {
            $this->model->setTeamId();
            $this->model->project_id = $this->prop('project_id');
            $this->model->submitted_by = auth()->id();
        }
    }

    public function body()
    {
        $model = $this->model instanceof Suggestion ? $this->model : new Suggestion();
        $projectId = $model->project_id ?: $this->prop("project_id");

        return _Rows(
            $model->reference
                ? _Html($model->reference)->class('text-xs text-gray-400 mb-1')
                : null,
            _Input('projects.title')->name('title')->required()
                ->onEnter(fn ($e) => $e->closeModal()->refresh(SuggestionsTable::ID)),
            _Textarea('projects.description')->name('body')->rows(4),
            _Columns(
                _Select('projects.status')->name('status')->options(ListValue::optionsForProject(ListValue::SUGGESTION_STATUS, $projectId))
                    ->default(SuggestionStatusEnum::NEW->value),
                _Input('projects.requester-name')->name('requester_name'),
            ),
            _Select('projects.requested-by-user')->name('requested_by_user_id')
                ->searchOptions(2, 'searchUsers', 'retrieveUser')
                ->comment('projects.requested-by-user-hint'),
            _Textarea('projects.internal-notes')->name('internal_notes')->rows(3),
            _MultiFile('projects.attachments')->name('files'),
            _FlexEnd(
                _SubmitButton('projects.save')->alert('projects.saved')->closeModal()->refresh(SuggestionsTable::ID),
            ),
        );
    }

    public function rules()
    {
        return [
            'title' => 'required|max:255',
        ];
    }
}
