<?php

namespace Condoedge\Projects\Models\Enums;

use Condoedge\Utils\Models\Traits\EnumKompo;

/**
 * Feature lifecycle: draft → analysis → ready (AI code-review done) → decomposed
 * (deliverables + GitHub issues) → in progress → done. Int slots kept stable.
 */
enum FeatureRequestStatusEnum: int
{
    use EnumKompo;

    case DRAFT = 1;
    case IN_ANALYSIS = 2;
    case READY = 3;
    case DECOMPOSED = 4;
    case IN_PROGRESS = 5;
    case DONE = 6;
    case REJECTED = 7;

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('projects.fr-draft'),
            self::IN_ANALYSIS => __('projects.fr-in-analysis'),
            self::READY => __('projects.fr-ready'),
            self::DECOMPOSED => __('projects.fr-decomposed'),
            self::IN_PROGRESS => __('projects.fr-in-progress'),
            self::DONE => __('projects.fr-done'),
            self::REJECTED => __('projects.fr-rejected'),
        };
    }

    /**
     * One colour per stage, on a ramp that reads as progress: neutral, then the two blues of
     * study, mauve once it is cut into tasks, then the greens of building and of done.
     *
     * It replaces three stages sharing bg-warning — Prête, Découpée and En cours were the same
     * amber, so the pill could not tell you where in the pipeline a request stood. Every value
     * is a SISC token from tailwind.config.js.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-graydark',
            self::IN_ANALYSIS => 'bg-infodark',
            self::READY => 'bg-infopale',
            self::DECOMPOSED => 'bg-mauvedark',
            self::IN_PROGRESS => 'bg-greenmain',
            self::DONE => 'bg-positive',
            self::REJECTED => 'bg-dangerdark',
        };
    }

    /** The same ramp as raw hex, for the stepper and the board, which paint inline. */
    public function hex(): string
    {
        return match ($this) {
            self::DRAFT => '#5F5F5F',
            self::IN_ANALYSIS => '#002D5A',
            self::READY => '#0078FF',
            self::DECOMPOSED => '#4E219B',
            self::IN_PROGRESS => '#006241',
            self::DONE => '#009243',
            self::REJECTED => '#9C2B22',
        };
    }

    /** How far along the pipeline, 1-based. Rejected sits outside it and returns null. */
    public function step(): ?int
    {
        return match ($this) {
            self::DRAFT => 1,
            self::IN_ANALYSIS => 2,
            self::READY => 3,
            self::DECOMPOSED => 4,
            self::IN_PROGRESS => 5,
            self::DONE => 6,
            self::REJECTED => null,
        };
    }

    /** The stages that form the line of the stepper, rejection excluded. */
    public static function pipeline(): array
    {
        return array_values(array_filter(self::cases(), fn ($c) => $c->step() !== null));
    }
}
