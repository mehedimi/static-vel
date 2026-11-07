# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 - 2026-11-07

### Added

- `query_parameters` config whitelist: only requests whose query parameter names are whitelisted are captured. Empty by default, so query strings are never cached unless explicitly allowed - protecting the storage directory from flooding.
- `static-vel` terminable middleware: captures `200` HTML responses to `GET` requests after they are sent, storing them as `<path>.html` with `/` stored as `index`.
- Query strings embedded verbatim into the file name (`/blog?category=x` -> `blog.category=x.html`), with traversal-safe validation mirrored on both the capture and the nginx side.
- `handle_inertia` config: captures Inertia JSON payloads as `<path>.json` so client-side navigations are served statically; partial reloads are never captured.
- Auth marker cookie (`static_vel_auth`, configurable): set on login, cleared on logout, and kept in sync by the optional `static-vel.auth` middleware. Authenticated visitors are never captured, and the nginx config bypasses the static cache for them.
- `static-vel:clear` command for deploy scripts.
- nginx serving guide with PHP-FPM and Laravel Octane / PHP-PM variants, hardened against URL traversal.
