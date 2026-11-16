<?php

namespace Condoedge\Projects\Kompo\Concerns;

/**
 * Keeps a list's search and filters across a round trip off the board.
 *
 * The board filters live in the table's own form, so a full page load — opening a feature, then
 * coming back — rebuilt the table empty and the filter was gone. This stores whatever the filter
 * fields hold, per table and per project, and hands it back on the next load: the query reads the
 * remembered value, and the fields default to it so the control shows what is actually applied.
 *
 * Telling a browse from a fresh load is the whole trick. Kompo OMITS an empty filter field from
 * the request, so a cleared search and a just-reloaded page look identical — both carry no `search`
 * key. A hidden marker field rides along on every filter browse (it holds a value, so it is sent)
 * but not on a boot (nothing is submitted then). Its presence means "these are the filters as the
 * user left them": read each field, absent now meaning cleared, and write the lot through — so a
 * clear sticks. Its absence means a fresh load, which reads back what was last applied.
 *
 * The host adds pmFilterMarker() to its top(), lists its fields in filterKeys(), and reads each
 * back with rememberedFilter().
 */
trait RemembersPmFilters
{
    private ?array $resolvedFilters = null;

    /** Rides along on a filter browse, absent on a boot — how a clear is told from a reload. */
    protected const FILTER_MARKER = '_pm_filter_live';

    /** The filter field names to remember, e.g. ['search', 'search_status']. */
    abstract protected function filterKeys(): array;

    /** A hidden field the host drops into its top() so a browse can be recognised. */
    protected function pmFilterMarker()
    {
        return _Hidden()->name(self::FILTER_MARKER, false)->value('1');
    }

    /** One bucket per table per project, so two boards never read each other's filter. */
    protected function filterSessionKey(): string
    {
        return 'pm_filters.' . static::ID . '.' . ((int) ($this->prop('project_id') ?? 0));
    }

    protected function rememberedFilter(string $key, $default = null)
    {
        $value = $this->resolveFilters()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    /** Reconcile request against the store once per render, and persist the result. */
    protected function resolveFilters(): array
    {
        if ($this->resolvedFilters !== null) {
            return $this->resolvedFilters;
        }

        $sessionKey = $this->filterSessionKey();
        $stored = session($sessionKey, []);
        $isBrowse = request()->has(self::FILTER_MARKER);
        $out = [];

        foreach ($this->filterKeys() as $key) {
            // On a browse the fields are authoritative: a value present is the pick, a value
            // absent is a field the user cleared. On a boot, nothing is touched and the store
            // stands.
            if ($isBrowse) {
                // Absent reads as '' (cleared). From the second change on, a serverFilter field is
                // posted twice and arrives as an array; the last value is the pick — storing the
                // array stringified to the literal "Array" and matched nothing.
                $value = request($key, '');
                $stored[$key] = is_array($value) ? end($value) : $value;
            }

            // Normalise on read too, so a session already holding an array is repaired rather than
            // stringified to "Array".
            $value = $stored[$key] ?? null;
            $out[$key] = is_array($value) ? end($value) : $value;
        }

        if ($isBrowse) {
            session()->put($sessionKey, $stored);
        }

        return $this->resolvedFilters = $out;
    }
}
