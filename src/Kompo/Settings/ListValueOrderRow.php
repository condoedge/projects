<?php

namespace Condoedge\Projects\Kompo\Settings;

use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * One draggable row inside ListValueOrderForm: a handle and the entry's own pill, nothing else.
 * Renaming and recolouring stay the job of ListValuesTable's "Modifier" modal — this row carries
 * no editable field at all, on purpose: _MultiForm's per-row save would need a real parent-child
 * relation to write through safely, which ListValue::siblings() deliberately is not (see there).
 */
class ListValueOrderRow extends Form
{
    use PmElements;

    public $model = ListValue::class;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function render()
    {
        $lv = $this->model;

        return _Flex(
            // The class vuedraggable's `handle` option looks for — dragging only starts from here.
            _Html('⠿')->class('js-row-move cursor-move text-graydark text-lg px-2 select-none'),
            $this->pmTint(_Pill($lv->displayName()), $lv->displayColor()),
        )->class('items-center gap-1 py-1');
    }
}
