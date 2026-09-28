<?php

namespace App\Support\PublicSite;

/**
 * A community's public website lives at `{slug}.{app host}`, e.g. `harbour-towers.property-flow.test`.
 */
class CommunityDomain
{
    public static function base(): string
    {
        return (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    /**
     * The route domain pattern, for `Route::domain()`.
     */
    public static function pattern(): string
    {
        return '{community:slug}.'.self::base();
    }

    public static function urlFor(string $slug): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';

        return $scheme.'://'.$slug.'.'.self::base();
    }
}
