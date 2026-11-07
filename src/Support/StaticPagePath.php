<?php

namespace Mehedi\StaticVel\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function config;
use function str;

class StaticPagePath
{
    /**
     * The maximum length of the query string token inside a file name.
     */
    protected const int MAX_QUERY_LENGTH = 128;

    /**
     * Resolve the absolute path of the static file for the given request,
     * or null when the request should not be captured.
     */
    public static function forRequest(Request $request, string $extension = 'html'): ?string
    {
        $segments = static::safeSegments($request->decodedPath());

        if ($segments === null) {
            return null;
        }

        // The raw query string, exactly as nginx sees it in $args. Note that
        // Request::getQueryString() is not usable here: Symfony canonicalizes
        // it (parse_str + ksort + rebuild), so the order would change and the
        // file name would never match what nginx reconstructs.
        $rawQuery = $request->server->get('QUERY_STRING');

        $query = static::safeQuery(is_string($rawQuery) ? $rawQuery : null);

        if ($query === null) {
            return null;
        }

        $file = ($segments->isEmpty() ? 'index' : $segments->implode('/'))
            .($query === '' ? '' : '.'.$query);

        return static::directory().$file.'.'.$extension;
    }

    /**
     * The configured storage directory, without a trailing slash.
     */
    public static function baseDirectory(): string
    {
        $path = config('static-vel.path');

        return rtrim(is_string($path) ? $path : '', '/\\');
    }

    protected static function directory(): string
    {
        return static::baseDirectory().'/';
    }

    /**
     * The raw query string as it is embedded in the file name, or an empty
     * string when the request has no query string. Null when the query
     * string is unsafe to cache.
     */
    protected static function safeQuery(?string $query): ?string
    {
        if (empty($query)) {
            return '';
        }

        // Only whitelisted parameter names may create cache files, so
        // visitors can never flood the storage directory with arbitrary
        // query strings.
        if (! static::queryParametersAreWhitelisted($query)) {
            return null;
        }

        // Only file-name safe characters are embedded verbatim. nginx
        // replicates this exact allowlist, so it can reconstruct the same
        // file name from $args.
        if (strlen($query) > static::MAX_QUERY_LENGTH || ! Str::isMatch('/^[A-Za-z0-9_=&%.+,~-]+$/', $query)) {
            return null;
        }

        // The allowlist on its own accepts encoded tricks like %2F or
        // %2E%2E. After decoding there must be no slashes and no dot
        // segments left, so the query token can never form a path of its
        // own. (nginx mirrors this by rejecting ".." in $args.)
        $decoded = rawurldecode($query);

        if (Str::contains($decoded, ['/', '\\', "\0", '..'])) {
            return null;
        }

        return $query;
    }

    /**
     * Every query parameter name in the raw query string must be
     * whitelisted in the `query_parameters` config. Names are compared as
     * they appear in the URL. With an empty whitelist (the default), query
     * strings are never captured.
     */
    protected static function queryParametersAreWhitelisted(string $query): bool
    {
        $allowed = (array) config('static-vel.query_parameters', []);

        if ($allowed === []) {
            return false;
        }

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            if (! in_array(explode('=', $pair, 2)[0], $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Split the request path into safe segments, or return null when it
     * attempts to traverse out of the storage directory.
     *
     * @return Collection<int, string>|null
     */
    protected static function safeSegments(string $path): ?Collection
    {
        $segments = str($path)->replace('\\', '/')->explode('/');

        if ($segments->contains(fn (string $segment) => $segment === '..' || str_contains($segment, "\0"))) {
            return null;
        }

        return $segments->reject(fn (string $segment) => in_array($segment, ['', '.']))->values();
    }
}
