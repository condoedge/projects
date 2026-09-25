<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

enum ComplexityEnum: int
{
    use EnumKompo;

    case TRIVIAL = 1;
    case SIMPLE = 2;
    case MODERATE = 3;
    case COMPLEX = 4;
    case VERY_COMPLEX = 5;

    public function label(): string
    {
        return match ($this) {
            self::TRIVIAL => __('projects.cx-trivial'),
            self::SIMPLE => __('projects.cx-simple'),
            self::MODERATE => __('projects.cx-moderate'),
            self::COMPLEX => __('projects.cx-complex'),
            self::VERY_COMPLEX => __('projects.cx-very-complex'),
        };
    }
}
