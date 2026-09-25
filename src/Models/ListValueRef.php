<?php

namespace Condoedge\Projects\Models;

use JsonSerializable;

/**
 * What a configurable column reads as: the stored integer, with the label and colour of the entry
 * behind it.
 *
 * Deliberately not an Eloquent model, although it stands for one. Kompo binds a form field by
 * asking `$value instanceof Model ? $value->getKeyName()` — handed a ListValue it took the row's
 * primary key instead of its `value` column, and every select came up empty on records that
 * already had one. A plain object serialises through jsonSerialize() as the integer the select's
 * options are keyed by.
 *
 * It answers label() and isKey() like the enums this replaced, so the pills and the comparisons
 * scattered through the module needed no change.
 */
final class ListValueRef implements JsonSerializable
{
    public function __construct(
        public readonly int $value,
        private readonly ?ListValue $entry = null,
    ) {
    }

    public function label(): string
    {
        return $this->entry?->displayName() ?? ('#' . $this->value);
    }

    public function displayColor(): ?string
    {
        return $this->entry?->displayColor();
    }

    public function isKey(string $systemKey): bool
    {
        return (bool) $this->entry?->isKey($systemKey);
    }

    /** The entry itself, for the rare caller that needs more than the label. */
    public function entry(): ?ListValue
    {
        return $this->entry;
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
