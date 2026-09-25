<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

enum SuggestionStatusEnum: int
{
    use EnumKompo;

    case NEW = 1;
    case UNDER_REVIEW = 2;
    case PROMOTED = 3;
    case DECLINED = 4;

    public function label(): string
    {
        return match ($this) {
            self::NEW => __('projects.sug-new'),
            self::UNDER_REVIEW => __('projects.sug-under-review'),
            self::PROMOTED => __('projects.sug-promoted'),
            self::DECLINED => __('projects.sug-declined'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NEW => 'bg-infopale',
            self::UNDER_REVIEW => 'bg-mauvedark',
            self::PROMOTED => 'bg-greenmain',
            self::DECLINED => 'bg-graydark',
        };
    }
}
