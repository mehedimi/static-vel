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
    | It defaults to a folder inside your `public/` directory so the nginx
    | config from the README can serve the files with a plain `try_files`
    | rule. Every captured page is stored as `<path>.html`.
    |
    */

    'path' => env('STATIC_VEL_PATH', storage_path('static-vel')),

    /*
    |--------------------------------------------------------------------------
    | Cacheable status codes
    |--------------------------------------------------------------------------
    |
    | Only responses with one of these status codes are captured. Redirects,
    | validation errors, and server errors are never written to disk.
    |
    */

    'cacheable_status_codes' => [200],

    /*
    |--------------------------------------------------------------------------
    | Cacheable content types
    |--------------------------------------------------------------------------
    |
    | Only responses whose `Content-Type` header starts with one of these
    | values are captured. `text/html` keeps JSON, Inertia payloads, and
    | file downloads out of the static cache.
    |
    */

    'cacheable_content_types' => ['text/html'],

    /*
    |--------------------------------------------------------------------------
    | Ignored query parameters
    |--------------------------------------------------------------------------
    |
    | Requests carrying these query parameters are still captured under their
    | base path, because they are tracking parameters that don't change the
    | page content (`/about?utm_source=twitter` stores `/about.html`).
    | Requests carrying any other query parameter are never captured, so the
    | first visitor can't poison the cache for everyone else.
    |
    */

    'ignored_query_parameters' => [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'fbclid',
        'gclid',
        'msclkid',
        'ttclid',
        'ref',
    ],

];
