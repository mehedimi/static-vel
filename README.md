# static-vel

Effortless static response caching for Laravel: capture rendered pages **after** the response is sent, then let nginx serve them as plain files — without touching Laravel at all.

```
                 ┌──────────── 1st visit ────────────┐
Browser ───────► nginx ──► Laravel ──► response sent │──► static-vel captures
                 │                                   │    and stores /about.html
                 │                                   ▼
                 └── 2nd visit: nginx serves ──► public/static-vel/about.html
                     the file directly. PHP never boots. ⚡
```

- **Opt-in per route** — pages are only captured where you attach the `static-vel` middleware.
- **Zero request overhead** — capture happens in `terminate()`, *after* the browser already got the response.
- **Web-server serving** — nginx returns the stored file for matching paths and falls back to Laravel when no file exists.
- **Inertia-safe** — Inertia visits (JSON navigations, partial reloads, version checks) always reach Laravel; only first / full page loads are served statically.

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13
- nginx (or any web server able to serve files with a fallback to Laravel)

## Installation

```bash
composer require mehedimi/static-vel
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=static-vel-config
```

## Capturing pages

Attach the `static-vel` middleware alias to any route or route group whose rendered HTML is identical for every visitor:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('static-vel')->group(function () {
    Route::view('/', 'welcome');
    Route::view('/about', 'about');
    Route::get('/products/{product}', [ProductController::class, 'show']);
});
```

The first `GET` request that returns a `200` HTML response is written to `public/static-vel/` **after** the response has been sent:

| Request path     | Stored file                    |
| ---------------- | ------------------------------ |
| `/`              | `public/static-vel/index.html` |
| `/about`         | `public/static-vel/about.html` |
| `/products/12`   | `public/static-vel/products/12.html` |

Files are written atomically (temporary file + `rename`), so nginx never picks up a half-written page.

### What is *not* captured

The middleware skips responses that would be unsafe or useless to cache:

- Non-`GET` requests
- Status codes other than `200` (redirects, 404s, 500s…)
- Non-HTML responses (JSON, downloads, streams — Inertia's JSON payloads are excluded automatically)
- Requests carrying the `X-Inertia` header (Inertia navigations)
- Requests with query parameters other than the [ignored tracking parameters](#configuration) (`/about?page=2` is never cached, `/about?utm_source=x` is stored as `about.html`)
- Paths attempting traversal (`/..%2Fetc/passwd`)

> [!WARNING]
> Only attach the middleware to pages that render the **same HTML for every visitor**. Never add it to authenticated dashboards or personalized pages — the first visitor's HTML would be served to everyone.

## Serving with nginx

This is the whole trick: when a captured file exists, nginx serves it and Laravel is never invoked. When it doesn't, the request falls through to Laravel as usual — which is also how the page gets captured in the first place.

```nginx
server {
    listen 80;
    server_name example.com;

    root /var/www/example.com/public;
    index index.php;

    # Internal redirect target for "hand this to Laravel".
    # 418 is deliberately obscure, so it never collides with a real response.
    error_page 418 = @laravel;

    # 1. The homepage has its own file (index.html).
    location = / {
        # Inertia navigations must reach Laravel (see "Inertia support").
        if ($http_x_inertia) {
            return 418;
        }

        try_files /static-vel/index.html @laravel;
    }

    # 2. Every other path: serve public/static-vel/<path>.html if it exists.
    location / {
        if ($http_x_inertia) {
            return 418;
        }

        try_files /static-vel$uri.html @laravel;
    }

    # 3. Don't expose the cache directory as a direct URL.
    location ^~ /static-vel/ {
        internal;
    }

    # 4. Fallback: let Laravel handle it.
    location @laravel {
        rewrite ^ /index.php last;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }
}
```

A request for `/about` therefore resolves as:

1. `public/static-vel/about.html` exists → nginx serves it, PHP never boots.
2. It doesn't exist → `@laravel` → Laravel renders the page → the middleware stores it after the response is sent.
3. Every future visitor gets the static file until the cache is cleared.

If you changed the storage location via the `path` config option, adjust the `try_files` rules (they assume the default `public/static-vel` directory).

## Inertia support

Inertia.js apps work out of the box, because of one hard rule: **requests carrying the `X-Inertia` header always reach Laravel.**

- **First / full page loads** (no `X-Inertia` header) are served from the static file. That's fine — it's the standard HTML shell with the `data-page` payload.
- **Inertia navigations** (client-side link clicks) send `X-Inertia: true` and expect a **JSON** response. The nginx config above returns those to Laravel, so partial reloads (`X-Inertia-Partial-Data`) and the asset-version check (Laravel answers `409` + `X-Inertia-Location` to force a full reload after a deploy) keep working exactly as designed.

> [!IMPORTANT]
> **Always clear the static cache after deploying.** The Inertia version and the hashed asset URLs (`/assets/app-abc123.js`) are baked into the captured HTML. A stale static file would reference deleted assets. Add this to your deploy script:

```bash
php artisan static-vel:clear
```

## Cache invalidation

```bash
php artisan static-vel:clear
```

Removes every captured page (the directory itself is kept). Run it after each deploy, or whenever a page's content changes.

Since nginx serves files directly, there is no TTL — a captured page stays until you clear it. Build the clear step into your deploy pipeline (e.g. as a [Laravel Deployer](https://deployer.org/) or Forge deploy script task).

## Configuration

Published as `config/static-vel.php`:

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `env('STATIC_VEL_ENABLED', true)` | Master switch. The middleware becomes a pass-through when `false`. |
| `path` | `env('STATIC_VEL_PATH', public_path('static-vel'))` | Directory captured pages are written to. |
| `cacheable_status_codes` | `[200]` | Responses with these status codes are captured. |
| `cacheable_content_types` | `['text/html']` | Responses whose `Content-Type` starts with one of these are captured. |
| `ignored_query_parameters` | `utm_*`, `fbclid`, `gclid`, … | Query parameters that don't prevent caching (tracking parameters). |

## Security notes

- Captured pages are served to **everyone**, including logged-in users — only opt in public pages.
- Path traversal (`/..%2F...`) is rejected; pages can never be written outside the configured directory.
- The nginx `internal` directive on `location ^~ /static-vel/` hides the cache directory from direct URLs, so `/static-vel/about.html` can't be requested directly (no duplicate content).

## Testing

```bash
composer test
composer lint   # Pint code style
```

## Roadmap

- [ ] `static-vel:warm` — pre-generate pages from a route list (useful right after a deploy + clear)
- [ ] Optional Inertia JSON caching for client-side navigations
- [ ] Apache `mod_rewrite` serving example
- [ ] Auto-invalidation on model / cache events

## License

static-vel is open-sourced software licensed under the [MIT license](LICENSE).
