<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

enum TaskStatusEnum: int
{
    use EnumKompo;

    case PENDING = 1;
    case IN_PROGRESS = 2;
    case BLOCKED = 3;
    case COMPLETED = 4;
    case CANCELLED = 5;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('projects.task-pending'),
            self::IN_PROGRESS => __('projects.task-in-progress'),
            self::BLOCKED => __('projects.task-blocked'),
            self::COMPLETED => __('projects.task-completed'),
            self::CANCELLED => __('projects.task-cancelled'),
        };
    }

    /** Kanban column dot / bar color — hex so it renders without relying on compiled Tailwind classes. */
    /**
     * The same ramp as a Tailwind class.
     *
     * hex() alone was not enough: a Kompo element drops ->attr(['style']) silently, so a pill
     * coloured that way rendered white text on nothing. Anything drawn by Kompo needs this;
     * hex() stays for the board and the stepper, which paint their own raw markup.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'bg-graydark',
            self::IN_PROGRESS => 'bg-infopale',
            self::BLOCKED => 'bg-dangerdark',
            self::COMPLETED => 'bg-positive',
            self::CANCELLED => 'bg-grayscout',
        };
    }

    public function hex(): string
    {
        return match ($this) {
            self::PENDING => '#5F5F5F',
            self::IN_PROGRESS => '#0078FF',
            self::BLOCKED => '#9C2B22',
            self::COMPLETED => '#009243',
            self::CANCELLED => '#B2B2B2',
        };
    }
}
