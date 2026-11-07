<?php

namespace Mehedimi\StaticVel\Support;

use Illuminate\Http\Request;

use function config;

class StaticPagePath
{
    /**
     * Resolve the absolute path of the static file for the given request,
     * or null when the request should not be captured.
     */
    public static function forRequest(Request $request): ?string
    {
        $file = static::relativeFileFor($request);

        if ($file === null) {
            return null;
        }

        return static::directory().$file;
    }

    /**
     * The file name relative to the configured directory, or null when the
     * request must not be captured (path traversal, non-cacheable query
     * string).
     */
    public static function relativeFileFor(Request $request): ?string
    {
        if (! static::queryIsCacheable($request)) {
            return null;
        }

        $segments = static::sanitizedSegments($request->decodedPath());

        if ($segments === null) {
            return null;
        }

        if ($segments === []) {
            return 'index.html';
        }

        return implode('/', $segments).'.html';
    }

    protected static function directory(): string
    {
        return rtrim((string) config('static-vel.path'), '/\\').'/';
    }

    /**
     * Split the request path into safe segments, or return null when it
     * attempts to traverse out of the storage directory.
     *
     * @return list<string>|null
     */
    protected static function sanitizedSegments(string $path): ?array
    {
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' || str_contains($segment, "\0")) {
                return null;
            }

            $segments[] = $segment;
        }

        return $segments;
    }

    /**
     * Determine whether the request's query string is safe to cache.
     */
    protected static function queryIsCacheable(Request $request): bool
    {
        $query = $request->getQueryString();

        if (empty($query)) {
            return true;
        }

        parse_str($query, $parameters);

        $ignored = (array) config('static-vel.ignored_query_parameters', []);

        foreach (array_keys($parameters) as $key) {
            if (! in_array($key, $ignored, true)) {
                return false;
            }
        }

        return true;
    }
}
