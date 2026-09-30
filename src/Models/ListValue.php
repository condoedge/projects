<?php

namespace Condoedge\Projects\Models;

use Condoedge\Projects\Models\Enums\FeatureRequestStatusEnum;
use Condoedge\Projects\Models\Enums\FeatureRequestTypeEnum;
use Condoedge\Projects\Models\Enums\PriorityEnum;
use Condoedge\Projects\Models\Enums\SuggestionStatusEnum;
use Condoedge\Utils\Models\Model;
use Illuminate\Support\Collection;
use Kompo\Auth\Contracts\Security\ScopedToTeam;
use Kompo\Auth\Models\Concerns\Security\BelongsToOneTeam;
use Kompo\Auth\Models\Teams\BelongsToTeamTrait;

/**
 * A configurable dropdown entry. One table, many lists, told apart by list_key.
 *
 * The enums stay the source of truth for defaults and for the entries code names directly;
 * rows here override their label and colour and add entries of the team's own. A team with no
 * rows reads exactly like the enums, so nothing has to be seeded ahead of time.
 */
class ListValue extends Model implements ScopedToTeam
{
    use BelongsToOneTeam, BelongsToTeamTrait;

    protected $table = 'pm_list_values';
    protected $guarded = [];

    public const FEATURE_REQUEST_TYPE = 'feature_request_type';
    public const FEATURE_REQUEST_STATUS = 'feature_request_status';
    public const SUGGESTION_STATUS = 'suggestion_status';
    public const PRIORITY = 'priority';
    public const TEAM_ROLE = 'team_role';
    // No backing enum, like TEAM_ROLE: a team's phases are entirely its own, so nothing here is
    // ever a "system" entry and nothing is seeded — the list simply starts empty.
    public const PHASE = 'phase';

    /**
     * Which enum seeds each list, and supplies the fallback when a team has no rows yet.
     * A list absent from here — team_role — simply starts empty and holds only what the team
     * puts in it. Nothing in the code names those entries, so none of them is protected.
     */
    public const LISTS = [
        self::FEATURE_REQUEST_TYPE => FeatureRequestTypeEnum::class,
        self::FEATURE_REQUEST_STATUS => FeatureRequestStatusEnum::class,
        self::SUGGESTION_STATUS => SuggestionStatusEnum::class,
        self::PRIORITY => PriorityEnum::class,
    ];

    /** Lists whose order carries meaning — "advance to the next stage" walks them. */
    public const ORDERED_LISTS = [self::FEATURE_REQUEST_STATUS];

    protected static function booted()
    {
        $drop = fn (self $row) => static::forget($row->list_key, (int) $row->team_id);

        static::saved($drop);
        static::deleted($drop);
    }

    public function scopeForList($query, string $listKey)
    {
        return $query->where('list_key', $listKey);
    }

    /**
     * Every entry sharing this row's team and list — what the drag-to-reorder form edits as one
     * group. A self-join on team_id rather than a real parent-child key, so _MultiForm's own
     * relation-save (which would set the "foreign key" — team_id here — to the anchor row's id)
     * must never run against it: ListValueOrderForm bypasses that with a selfPost of its own.
     */
    public function siblings()
    {
        return $this->hasMany(static::class, 'team_id', 'team_id')
            ->where('list_key', $this->list_key)
            ->orderBy('position')->orderBy('value');
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    /** The enum case a seeded entry stands for, or null once it is a team's own entry. */
    public function enumCase()
    {
        $enum = static::LISTS[$this->list_key] ?? null;

        if (!$enum || !$this->system_key) {
            return null;
        }

        foreach ($enum::cases() as $case) {
            if ($case->name === $this->system_key) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Falls back to the enum rather than storing its label, so an untouched entry follows the
     * interface language instead of freezing into whichever locale first seeded it.
     */
    public function displayName(): string
    {
        return $this->getAttributeValue('name')
            ?: ($this->enumCase()?->label() ?? ('#' . $this->getAttributeValue('value')));
    }

    /**
     * Read through getAttributeValue, never $this->color: a method named after a column makes
     * Eloquent treat it as a relation the moment the attribute is missing — as it is on a new
     * entry — and calling color() from here would then loop back into itself.
     */
    public function displayColor(): ?string
    {
        if ($colour = $this->getAttributeValue('color')) {
            return $colour;
        }

        $case = $this->enumCase();

        return $case && method_exists($case, 'color') ? $case->color() : null;
    }

    /**
     * Hex equivalents of the fixed Tailwind palette the colour wheel replaced — an enum's own
     * colour is a Tailwind class, which nothing that paints inline (the colour picker, the
     * stepper) can read directly.
     */
    public const TAILWIND_HEX = [
        'bg-gray-400' => '#9CA3AF',
        'bg-info' => '#002CDE',
        'bg-warning' => '#FFB400',
        'bg-positive' => '#009243',
        'bg-danger' => '#FF4637',
        'bg-level1' => '#006241',
        'bg-graydark' => '#5F5F5F',
    ];

    /** displayColor(), resolved to an actual hex value regardless of how it is stored. */
    public function displayHex(): ?string
    {
        $color = $this->displayColor();

        if (!$color) {
            return null;
        }

        return str_starts_with($color, '#') ? $color : (self::TAILWIND_HEX[$color] ?? null);
    }

    /**
     * label() keeps the enum's name so the pills that call it need no change. There is
     * deliberately no color() to match: a method named after the `color` column makes Eloquent
     * resolve it as a relation whenever the attribute is absent, which it is on a new entry.
     * Callers use displayColor().
     */
    public function label(): string
    {
        return $this->displayName();
    }

    /** How code names an entry now that identity comparison against an enum no longer holds. */
    public function isKey(string $systemKey): bool
    {
        return $this->system_key === $systemKey;
    }


    /**
     * Every entry of a list, seeded from the enum the first time it is asked for.
     * Seeding on read rather than in the migration keeps teams created later correct too.
     */
    public static function forList(string $listKey, int $teamId): Collection
    {
        // Memoised for the request: the cast resolves a row per record per column, so a table of
        // fifty feature requests would otherwise fire a hundred and fifty identical queries.
        $cacheKey = $listKey . ':' . $teamId;

        if (isset(static::$cache[$cacheKey])) {
            return static::$cache[$cacheKey];
        }

        $rows = static::loadRows($listKey, $teamId);

        if ($rows->isEmpty()) {
            static::seedDefaults($listKey, $teamId);
            $rows = static::loadRows($listKey, $teamId);
        }

        return static::$cache[$cacheKey] = $rows;
    }

    protected static array $cache = [];

    /** Drops the memo after an edit, so the table redraws with what was just saved. */
    public static function forget(string $listKey, int $teamId): void
    {
        unset(static::$cache[$listKey . ':' . $teamId]);
    }

    protected static function loadRows(string $listKey, int $teamId): Collection
    {
        return static::asSystemOperation()
            ->where('team_id', $teamId)
            ->forList($listKey)
            ->orderBy('position')->orderBy('value')
            ->get();
    }

    /** Ready for _Select()->options(). */
    public static function optionsFor(string $listKey, int $teamId): Collection
    {
        return static::forList($listKey, $teamId)
            ->mapWithKeys(fn (self $row) => [$row->value => $row->displayName()]);
    }

    /**
     * Same as optionsFor(), addressed by project — the forms know which project they are on,
     * not which team owns it. Falls back to the enum when the project cannot be resolved, so a
     * select is never empty.
     */
    public static function optionsForProject(string $listKey, $projectId): Collection
    {
        $teamId = $projectId
            ? (int) Project::asSystemOperation()->where('id', $projectId)->value('team_id')
            : 0;

        if (!$teamId) {
            // A list with no enum behind it has nothing to fall back on, and an empty select is
            // the honest answer: its entries only exist once a team creates them.
            $enum = static::LISTS[$listKey] ?? null;

            return $enum
                ? collect($enum::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])
                : collect();
        }

        return static::optionsFor($listKey, $teamId);
    }

    public static function findValue(string $listKey, int $teamId, $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return static::forList($listKey, $teamId)->firstWhere('value', (int) $value);
    }

    /** The entry a given system key resolves to for this team — how code names an entry now. */
    public static function findSystem(string $listKey, int $teamId, string $systemKey): ?self
    {
        return static::forList($listKey, $teamId)->firstWhere('system_key', $systemKey);
    }

    /**
     * Reachable only by picking them outright — "advance" walks past them. Mirrors the flow
     * array FeatureRequestsTable used to hold, which listed six stages and left REJECTED out.
     */
    protected const OUT_OF_FLOW = [
        self::FEATURE_REQUEST_STATUS => ['REJECTED'],
        self::SUGGESTION_STATUS => ['DECLINED'],
    ];

    /**
     * The ordered, "in flow" entries of an ordered list — what a stepper walks. A terminal
     * side-branch like "Rejected" is picked outright, never stepped through, so it stays out of
     * this even though forList() still returns it.
     */
    public static function pipeline(string $listKey, int $teamId): Collection
    {
        $skip = static::OUT_OF_FLOW[$listKey] ?? [];

        return static::forList($listKey, $teamId)
            ->reject(fn (self $row) => in_array($row->system_key, $skip, true))
            ->values();
    }

    /** Next stage of an ordered list, or null at the end. Replaces the hardcoded flow arrays. */
    public static function next(string $listKey, int $teamId, $value): ?self
    {
        $all = static::pipeline($listKey, $teamId);

        $i = $all->search(fn (self $row) => (int) $row->value === (int) $value);

        return ($i !== false && $i < $all->count() - 1) ? $all[$i + 1] : null;
    }

    /** The first free slot, so a team's own entries never collide with the enum's integers. */
    public static function nextFreeValue(string $listKey, int $teamId): int
    {
        return (int) static::asSystemOperation()
            ->where('team_id', $teamId)->forList($listKey)->max('value') + 1;
    }

    protected static function seedDefaults(string $listKey, int $teamId): void
    {
        $enum = static::LISTS[$listKey] ?? null;

        if (!$enum) {
            return;
        }

        foreach ($enum::cases() as $i => $case) {
            $row = new static();
            $row->team_id = $teamId;
            $row->list_key = $listKey;
            $row->value = $case->value;
            // name and color stay null: displayName() and displayColor() read the enum until
            // someone overrides them here.
            $row->system_key = $case->name;
            $row->position = $i;
            $row->save();
        }
    }
}
