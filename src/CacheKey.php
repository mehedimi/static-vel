<?php

namespace Mehedi\StaticVel;

use Illuminate\Http\Request;

use function app;
use function array_filter;
use function array_unique;
use function array_values;
use function is_array;

class CacheKey
{
    /**
     * The request attribute the allowed query keys are stored under. State
     * lives on the request itself, so it is scoped to exactly one request
     * and can never leak between Octane / PHP-PM worker requests.
     */
    protected const string ATTRIBUTE = 'static_vel_allowed_query_keys';

    /**
     * Allow the given query parameter names to become part of the cache
     * key for the current request. By default, no query parameters are
     * allowed and query strings never produce cache files.
     *
     * Validate the parameter values with Laravel's validation API first
     * and pass the validated keys - only values that passed your rules can
     * end up in a cache file name, so malicious visitors cannot inject
     * data into the static cache key:
     *
     *   $validated = $request->validate([
     *       'category' => ['nullable', 'string', 'max:50'],
     *       'page' => ['nullable', 'integer', 'min:1'],
     *   ]);
     *
     *   CacheKey::allowQueryKeys(array_keys($validated));
     *
     * @param  list<string>  $keys
     */
    public static function allowQueryKeys(array $keys): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $request = app(Request::class);

        $allowed = array_values(array_unique([
            ...static::allowedQueryKeys($request),
            ...array_values(array_filter($keys, 'is_string')),
        ]));

        $request->attributes->set(self::ATTRIBUTE, $allowed);
    }

    /**
     * The query parameter names allowed for the current request.
     *
     * @return list<string>
     */
    public static function allowedQueryKeys(Request $request): array
    {
        $keys = $request->attributes->get(self::ATTRIBUTE, []);

        if (! is_array($keys)) {
            return [];
        }

        return array_values(array_filter($keys, 'is_string'));
    }
}
