<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

enum ProjectStatusEnum: int
{
    use EnumKompo;

    case ACTIVE = 1;
    case ARCHIVED = 2;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('projects.status-active'),
            self::ARCHIVED => __('projects.status-archived'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-positive',
            self::ARCHIVED => 'bg-gray-400',
        };
    }
}
