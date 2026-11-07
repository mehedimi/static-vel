<?php

namespace Mehedi\StaticVel\Support;

use Illuminate\Http\Request;

use function config;

class StaticPagePath
{
    /**
     * The maximum length of the query string token inside a file name.
     */
    protected const MAX_QUERY_LENGTH = 128;

    /**
     * Resolve the absolute path of the static file for the given request,
     * or null when the request should not be captured.
     */
    public static function forRequest(Request $request, string $extension = 'html'): ?string
    {
        $segments = static::sanitizedSegments($request->decodedPath());

        if ($segments === null) {
            return null;
        }

        // The raw query string, exactly as nginx sees it in $args. Note that
        // Request::getQueryString() is not usable here: Symfony canonicalizes
        // it (parse_str + ksort + rebuild), so the order would change and the
        // file name would never match what nginx reconstructs.
        $query = static::queryToken($request->server->get('QUERY_STRING'));

        if ($query === null) {
            return null;
        }

        $file = ($segments === [] ? 'index' : implode('/', $segments))
            .($query === '' ? '' : '.'.$query);

        return static::directory().$file.'.'.$extension;
    }

    protected static function directory(): string
    {
        return rtrim((string) config('static-vel.path'), '/\\').'/';
    }

    /**
     * The raw query string as it is embedded in the file name, or an empty
     * string when the request has no query string. Null when the query
     * string is unsafe to cache.
     */
    protected static function queryToken(?string $query): ?string
    {
        if (empty($query)) {
            return '';
        }

        if (strlen($query) > static::MAX_QUERY_LENGTH) {
            return null;
        }

        // Only file-name safe characters are embedded verbatim. nginx
        // replicates this exact allowlist, so it can reconstruct the same
        // file name from $args.
        if (! preg_match('/^[A-Za-z0-9_=&%.+,~-]+$/', $query)) {
            return null;
        }

        // The allowlist on its own accepts encoded tricks like %2F. After
        // decoding there must be no slashes left, so the query token can
        // never form a path segment of its own.
        $decoded = rawurldecode($query);

        if (str_contains($decoded, '/') || str_contains($decoded, '\\') || str_contains($decoded, "\0")) {
            return null;
        }

        return $query;
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
}
