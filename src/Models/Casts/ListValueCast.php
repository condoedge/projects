<?php

namespace Condoedge\Projects\Models\Casts;

use BackedEnum;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\ListValueRef;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Casts a stored integer to a ListValueRef — the value plus the label and colour of the entry
 * behind it. Written as a cast rather than as accessors so the change stays invisible to calling
 * code: the ref answers label() like the enums did, and set() still accepts an enum case, so
 * `$fr->status = FeatureRequestStatusEnum::DONE` keeps working in the GitHub sync and the
 * import commands.
 *
 * What it cannot preserve is identity — `$fr->status === SomeEnum::DRAFT` is now always false.
 * Those comparisons read `$fr->status?->is('DRAFT')` instead.
 */
class ListValueCast implements CastsAttributes
{
    public function __construct(protected string $listKey)
    {
    }

    public function get($model, string $key, $value, array $attributes): ?ListValueRef
    {
        // Most tables here carry team_id themselves; a row that does not — a team member — is
        // asked for it, and answers with the team it belongs to.
        $teamId = (int) ($attributes['team_id'] ?? $model->team_id ?? 0);

        if ($value === null || !$teamId) {
            return null;
        }

        $entry = ListValue::findValue($this->listKey, $teamId, $value);

        return new ListValueRef((int) $value, $entry);
    }

    public function set($model, string $key, $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ListValueRef) {
            return $value->value;
        }

        if ($value instanceof ListValue) {
            return (int) $value->value;
        }

        if ($value instanceof BackedEnum) {
            return (int) $value->value;
        }

        return (int) $value;
    }
}
