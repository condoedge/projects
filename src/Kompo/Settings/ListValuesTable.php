<?php

namespace Condoedge\Projects\Kompo\Settings;

use Condoedge\Projects\Models\ListValue;
use Condoedge\Utils\Kompo\Common\WhiteTable;

/**
 * One table for every configurable dropdown — which list it edits comes from the store.
 * Shaped after Condoedge\Finance\Kompo\ExpenseReports\ExpenseReportTypesTable.
 */
class ListValuesTable extends WhiteTable
{
    protected $listKey;
    protected $teamId;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        $this->listKey = $this->prop('list_key');
        $this->teamId = (int) $this->prop('team_id');

        // Four of these sit on the settings tab at once, so the id has to tell them apart.
        $this->id = 'pm-list-values-' . $this->listKey;
    }

    public function query()
    {
        // Reading through the model first materialises the enum defaults for a team that has
        // never opened this list.
        ListValue::forList($this->listKey, $this->teamId);

        return ListValue::asSystemOperation()
            ->where('team_id', $this->teamId)
            ->forList($this->listKey)
            ->orderBy('position')->orderBy('value');
    }

    public function top()
    {
        return _Rows(
            _Button('projects.add-list-value')->icon('plus')
                ->selfGet('getListValueForm')->inModal(),
        )->class('mb-3');
    }

    public function headers()
    {
        return [
            _Th('projects.list-value-name'),
            _Th('projects.list-value-preview')->class('w-40'),
            _Th()->class('w-8'),
        ];
    }

    public function render($listValue)
    {
        return _TableRow(
            _Flex(
                _Html($listValue->displayName())->class('font-medium'),
                !$listValue->isSystem() ? null :
                    _Html('projects.system-entry')->class('text-xs text-gray-400'),
            )->class('gap-2 items-center'),

            $listValue->displayColor()
                ? _Pill($listValue->displayName())->class($listValue->displayColor() . ' text-white')
                : _Html('—'),

            _TripleDotsDropdown(
                _Link('projects.edit')
                    ->selfGet('getListValueForm', ['id' => $listValue->id])->inModal(),

                // The GitHub sync and the imports reach for system entries by name. Deleting one
                // would break them without a word, so the option is simply not offered — the
                // label and colour stay editable through the form above.
                $listValue->isSystem() ? null :
                    _DeleteLink('projects.delete')->byKey($listValue)->class('text-danger'),
            ),
        );
    }

    public function getListValueForm($id = null)
    {
        return new ListValueForm($id, [
            'list_key' => $this->listKey,
            'team_id' => $this->teamId,
        ]);
    }
}
