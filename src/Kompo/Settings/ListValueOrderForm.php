<?php

namespace Condoedge\Projects\Kompo\Settings;

use Condoedge\Projects\Models\ListValue;
use Condoedge\Utils\Kompo\Common\Modal;

/**
 * Drag to reorder a list's entries — for the one place order carries meaning: an ORDERED_LISTS
 * key like feature_request_status, whose sequence is the stepper's own left-to-right walk.
 *
 * Deliberately not a _SubmitButton(): that runs Kompo's normal save cycle, which for a
 * _MultiForm() field calls setRelationFromRequest() — and that sets siblings()'s "foreign key"
 * (team_id, since the relation is a same-column self-join rather than real parent-child) to this
 * form's own anchor row id on every child, silently moving every entry to a wrong team. The
 * button here is a plain selfPost that writes only what actually changed: position, from the
 * order the rows were submitted in.
 */
class ListValueOrderForm extends Modal
{
    public $_Title = 'projects.reorder-list';
    public $model = ListValue::class;

    protected $listKey;
    protected $teamId;

    protected $noHeaderButtons = true;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->listKey = $this->prop('list_key');
        $this->teamId = (int) $this->prop('team_id');

        // Any row of the list anchors the form; forList() seeds the enum defaults first, so a
        // team that has never opened this list still has something to anchor on.
        $anchor = ListValue::forList($this->listKey, $this->teamId)->first();

        if ($anchor) {
            $this->model($anchor);
        }
    }

    public function body()
    {
        return _Rows(
            _Html('projects.reorder-list-hint')->class('text-sm text-gray-500 mb-3'),

            _MultiForm()->noLabel()->noAdding()->name('siblings')
                ->formClass(ListValueOrderRow::class),

            _FlexEnd(
                _Button('projects.save')->selfPost('saveOrder')->withAllFormValues()
                    ->alert('projects.saved')
                    ->closeModal()->refresh('pm-list-values-' . $this->listKey),
            ),
        );
    }

    /**
     * The rows arrive keyed by their new position (0, 1, 2…) — vuedraggable reordered the
     * client-side array, and _MultiForm submits each row under its array index. Writing position
     * straight from that index is the whole job; nothing else on a row was ever editable here.
     */
    public function saveOrder()
    {
        collect(request('siblings', []))
            ->values()
            ->each(function ($row, $index) {
                if (!empty($row['multiFormKey'])) {
                    ListValue::asSystemOperation()
                        ->where('id', $row['multiFormKey'])
                        ->where('team_id', $this->teamId)
                        ->where('list_key', $this->listKey)
                        ->update(['position' => $index]);
                }
            });

        ListValue::forget($this->listKey, $this->teamId);
    }
}
