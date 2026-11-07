<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Static capture
    |--------------------------------------------------------------------------
    |
    | Master switch for the package. When disabled, the middleware becomes a
    | pass-through and nothing is written to disk. Useful to turn the whole
    | feature off during local development or a bad deploy.
    |
    */

    'enabled' => env('STATIC_VEL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Storage path
    |--------------------------------------------------------------------------
    |
    | The directory captured responses are written to, keyed by request path.
    | HTML pages are stored as `<path>.html`, Inertia payloads as
    | `<path>.json`, and `/` is stored as `index`. A query string is
    | embedded verbatim into the file name (`/blog?x=1` -> `blog.x=1.html`).
    |
    */

    'path' => env('STATIC_VEL_PATH', storage_path('static-vel')),

    /*
    |--------------------------------------------------------------------------
    | Handle Inertia
    |--------------------------------------------------------------------------
    |
    | When enabled, the JSON payload of Inertia visits (requests carrying the
    | `X-Inertia` header) is captured as `<path>.json`, so client-side
    | navigations can be served statically as well. Inertia partial reloads
    | are never captured - they exist to fetch fresh data.
    |
    */

    'handle_inertia' => env('STATIC_VEL_HANDLE_INERTIA', true),

    /*
    |--------------------------------------------------------------------------
    | Auth marker cookie
    |--------------------------------------------------------------------------
    |
    | nginx cannot check Laravel sessions, but it can inspect cookies. The
    | package sets this marker cookie on login and clears it on logout, and
    | the nginx config from the README skips the static cache whenever the
    | request carries it - so authenticated visitors always receive fresh
    | content from the application, while guests are served the cached page.
    |
    | The capture middleware also skips responses for requests carrying the
    | cookie (or for authenticated users), so personalized content can never
    | overwrite the guest cache.
    |
    | Set it to `null` to disable the auth handling. Avoid dashes in the
    | name - nginx exposes cookies as `$cookie_<name>` variables.
    |
    */

    'auth_cookie' => env('STATIC_VEL_AUTH_COOKIE', 'static_vel_auth'),

    /*
    |--------------------------------------------------------------------------
    | Whitelisted query parameters
    |--------------------------------------------------------------------------
    |
    | Every query parameter name must be whitelisted here for the request to
    | be captured - otherwise the request is always handled by the
    | application. This prevents visitors from flooding the storage
    | directory by requesting endless made-up query strings.
    |
    | Values stay dynamic: `/blog?category=x` and `/blog?category=y` are
    | cached as two files, as long as `category` is whitelisted and the
    | query string stays within the safety rules (see README).
    |
    | With the default empty whitelist, query strings are never captured.
    |
    */

    'query_parameters' => [
        //
    ],

];
