<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

enum PriorityEnum: int
{
    use EnumKompo;

    case LOW = 1;
    case MEDIUM = 2;
    case HIGH = 3;
    case CRITICAL = 4;

    public function label(): string
    {
        return match ($this) {
            self::LOW => __('projects.priority-low'),
            self::MEDIUM => __('projects.priority-medium'),
            self::HIGH => __('projects.priority-high'),
            self::CRITICAL => __('projects.priority-critical'),
        };
    }

    /**
     * Kept for the few places that still fill a shape with it (the Kanban's left edge).
     * Everywhere a status sits alongside, use bar()/textColor() instead: two filled pills
     * side by side read as one language, and status is the one that owns that language.
     */
    public function color(): string
    {
        return match ($this) {
            self::LOW => 'bg-graydark',
            self::MEDIUM => 'bg-warningdark',
            self::HIGH => 'bg-dangerdark',
            self::CRITICAL => 'bg-danger',
        };
    }

    /** Text colour of the label, on white. All four clear 4.5:1. */
    public function textColor(): string
    {
        return match ($this) {
            self::LOW => 'text-graydark',
            self::MEDIUM => 'text-warningdark',
            self::HIGH => 'text-dangerdark',
            self::CRITICAL => 'text-dangerdark',
        };
    }

    /** Raw hex, for the board and anything painting inline. */
    public function hex(): string
    {
        return match ($this) {
            self::LOW => '#5F5F5F',
            self::MEDIUM => '#b76404',
            self::HIGH => '#9C2B22',
            self::CRITICAL => '#9C2B22',
        };
    }

    /** Critical earns a filled shape; the rest stay a bar plus a word. */
    public function isCritical(): bool
    {
        return $this === self::CRITICAL;
    }
}
