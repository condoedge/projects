<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

/** Analysis tasks bring a feature to "ready"; deliverables are small, measurable units that become GitHub issues. */
enum TaskKindEnum: int
{
    use EnumKompo;

    case ANALYSIS = 1;
    case DELIVERABLE = 2;

    public function label(): string
    {
        return match ($this) {
            self::ANALYSIS => __('projects.kind-analysis'),
            self::DELIVERABLE => __('projects.kind-deliverable'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ANALYSIS => 'bg-info',
            self::DELIVERABLE => 'bg-positive',
        };
    }
}
