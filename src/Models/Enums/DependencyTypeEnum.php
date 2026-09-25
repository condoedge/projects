<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

/** Task precedence relationship (PMBOK). FS is the default used by the Gantt. */
enum DependencyTypeEnum: int
{
    use EnumKompo;

    case FS = 1; // finish-to-start
    case SS = 2; // start-to-start
    case FF = 3; // finish-to-finish
    case SF = 4; // start-to-finish

    public function label(): string
    {
        return match ($this) {
            self::FS => 'Finish → Start',
            self::SS => 'Start → Start',
            self::FF => 'Finish → Finish',
            self::SF => 'Start → Finish',
        };
    }
}
