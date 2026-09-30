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

        // New entries join an ordered list at the end rather than position 0 — one drag to move
        // it into place, from there, instead of several to move it out of the front.
        if (in_array($this->listKey, ListValue::ORDERED_LISTS, true)) {
            $this->model->position = (int) ListValue::asSystemOperation()
                ->where('team_id', $this->teamId)->forList($this->listKey)->max('position') + 1;
        }
    }

    public function body()
    {
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

            _ColorPicker('projects.list-value-color')->name('color')
                ->default($this->colorPickerDefault($existing)),

            // Order itself is set by dragging in ListValueOrderForm now, not by typing a number.
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

    /**
     * Whatever colour the wheel should open on. An entry already holding a hex value (saved
     * through the wheel before) opens on it directly; one that only ever carried its enum's
     * Tailwind class opens on that class's hex equivalent, so editing starts from the colour
     * actually showing today rather than black. A brand new entry has nothing to show yet.
     */
    protected function colorPickerDefault(?ListValue $existing): ?string
    {
        return $existing?->displayHex();
    }
}
