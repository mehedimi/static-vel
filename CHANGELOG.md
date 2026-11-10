# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Changed

- The auth marker cookie is exempted from Laravel's cookie encryption (`EncryptCookies::except()`): it only ever holds the constant `1`, so there is nothing to protect and nginx sees a plain value instead of an opaque encrypted blob. A plain incoming cookie now also survives request decryption untouched. The package registers the exemption itself - no entry in the application's `EncryptCookies` exceptions is needed.

## 1.0.0 - 2026-11-07

### Added

- `Capture::skip()`: lets the application opt a specific response out of the static capture at runtime, per request.
- `CacheKey::allowQueryKeys()` service: query strings are never cached by default. Applications opt in per request - typically `CacheKey::allowQueryKeys(array_keys($request->validate([...])))` - so only validated parameter names and values can end up in cache file names, and visitors cannot flood the storage directory with made-up query strings.
- `static-vel` terminable middleware: captures `200` HTML responses to `GET` requests after they are sent, storing them as `<path>.html` with `/` stored as `index`.
- Query strings embedded verbatim into the file name (`/blog?category=x` -> `blog.category=x.html`), with traversal-safe validation mirrored on both the capture and the nginx side.
- `handle_inertia` config: captures Inertia JSON payloads as `<path>.json` so client-side navigations are served statically; partial reloads are never captured.
- Auth marker cookie (`static_vel_auth`, configurable): set on login, cleared on logout, and kept in sync by the optional `static-vel.auth` middleware. Authenticated visitors are never captured, and the nginx config bypasses the static cache for them.
- `static-vel:clear` command for deploy scripts.
- `static-vel:routes` command listing every route covered by static rendering, with the static file each route writes.

### Fixed

- `static-vel:clear` reported success even when the captured files could not be deleted - Laravel's `File::deleteDirectory()` never reports failure. The command now deletes the files itself, verifies the directory is empty and exits with a failure code when it is not, so deploy scripts stop instead of silently serving stale pages.
- nginx serving guide with PHP-FPM and Laravel Octane / PHP-PM variants, hardened against URL traversal.
