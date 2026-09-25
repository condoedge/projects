<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

enum ConfidenceEnum: int
{
    use EnumKompo;

    case LOW = 1;
    case MEDIUM = 2;
    case HIGH = 3;

    public function label(): string
    {
        return match ($this) {
            self::LOW => __('projects.cf-low'),
            self::MEDIUM => __('projects.cf-medium'),
            self::HIGH => __('projects.cf-high'),
        };
    }
}
