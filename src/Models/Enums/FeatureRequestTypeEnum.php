<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

enum FeatureRequestTypeEnum: int
{
    use EnumKompo;

    case FEATURE = 1;
    case CHANGE = 2;

    public function label(): string
    {
        return match ($this) {
            self::FEATURE => __('projects.type-feature'),
            self::CHANGE => __('projects.type-change'),
        };
    }
}
