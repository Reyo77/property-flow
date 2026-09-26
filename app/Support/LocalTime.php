<?php

namespace App\Support;

use App\Models\Community;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Converts between what someone types into a date-time field (the community's own wall
 * clock) and what is stored (UTC), so a ballot set to close "at 5pm" closes at 5pm there, and
 * shows stored instants back on that wall clock.
 */
final class LocalTime
{
    public static function toUtc(string $local, Community $community): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $local, $community->timezone)?->utc()
            ?? CarbonImmutable::parse($local, $community->timezone)->utc();
    }

    public static function forInput(CarbonInterface $moment, Community $community): string
    {
        return CarbonImmutable::parse($moment)->setTimezone($community->timezone)->format('Y-m-d\TH:i');
    }

    /**
     * The moment on the community's wall clock, for formatting.
     */
    public static function local(CarbonInterface $moment, Community $community): CarbonImmutable
    {
        return CarbonImmutable::parse($moment)->setTimezone($community->timezone);
    }

    public static function display(CarbonInterface $moment, Community $community): string
    {
        return CarbonImmutable::parse($moment)->setTimezone($community->timezone)->format('D, M j, Y · g:ia');
    }
}
