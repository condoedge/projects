<?php

namespace Condoedge\Projects\Kompo\Settings;

use Condoedge\Projects\Models\ListValue;
use Condoedge\Utils\Kompo\Common\Modal;

class ListValueForm extends Modal
{
    public $_Title = 'projects.list-value';
    public $model = ListValue::class;

    protected $listKey;
    protected $teamId;

    /** The palette the enums already draw from — no new colours introduced here. */
    protected const COLORS = [
        'bg-gray-400' => 'projects.color-grey',
        'bg-info' => 'projects.color-blue',
        'bg-warning' => 'projects.color-orange',
        'bg-positive' => 'projects.color-green',
        'bg-danger' => 'projects.color-red',
        'bg-level1' => 'projects.color-primary',
    ];

    // The base Modal adds its own "Sauvegarder" in the header, on top of the save button
    // this form already puts at the bottom. Two buttons for one submission.
    protected $noHeaderButtons = true;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->listKey = $this->prop('list_key');
        $this->teamId = (int) $this->prop('team_id');

        if ($this->modelKey()) {
            $this->model(ListValue::asSystemOperation()->findOrFail($this->modelKey()));
        }
    }

    public function beforeSave()
    {
        if ($this->model->id) {
            return;
        }

        $this->model->team_id = $this->teamId;
        $this->model->list_key = $this->listKey;

        // Past the enum's own integers, so a team's entries can never collide with a system one.
        $this->model->value = ListValue::nextFreeValue($this->listKey, $this->teamId);
    }

    public function body()
    {
        $isOrdered = in_array($this->listKey, ListValue::ORDERED_LISTS, true);
        $existing = ($this->model instanceof ListValue && $this->model->id) ? $this->model : null;
        $isSystem = (bool) $existing?->isSystem();

        return _Rows(
            !$isSystem ? null : _Html('projects.system-entry-hint')
                ->class('text-sm text-gray-500 mb-3'),

            // Left empty, a seeded entry keeps following the interface language. Filling it in
            // pins the label for this team — which is the point, but only when asked for.
            _Input('projects.list-value-name')->name('name')
                ->placeholder($existing?->displayName())
                ->comment($existing?->isSystem() ? 'projects.list-value-name-hint' : null)
                ->onEnter(fn ($e) => $e->closeModal()->refresh('pm-list-values-' . $this->listKey)),

            _Select('projects.list-value-color')->name('color')
                ->options(collect(self::COLORS)->map(fn ($label) => __($label))),

            !$isOrdered ? null : _InputNumber('projects.list-value-position')->name('position')
                ->min(0)->default($existing?->position ?? 0)
                ->comment('projects.list-value-position-hint'),

            _FlexEnd(
                _SubmitButton('projects.save')->alert('projects.saved')->closeModal()
                    ->refresh('pm-list-values-' . $this->listKey),
            ),
        );
    }

    public function rules()
    {
        // A team's own entry needs a name; a seeded one may stay empty and keep following the enum.
        $isSystem = $this->model instanceof ListValue && $this->model->id && $this->model->isSystem();

        return [
            'name' => ($isSystem ? 'nullable' : 'required') . '|max:255',
        ];
    }
}
