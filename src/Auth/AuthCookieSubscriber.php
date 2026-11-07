<?php

namespace Mehedi\StaticVel\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cookie;

use function config;

class AuthCookieSubscriber
{
    /**
     * The lifetime of the marker cookie, in minutes.
     */
    public const int COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
        ];
    }

    /**
     * Mark the visitor as authenticated after a login.
     */
    public function handleLogin(Login $event): void
    {
        static::queueMarker();
    }

    /**
     * Remove the marker after a logout.
     */
    public function handleLogout(Logout $event): void
    {
        static::queueForget();
    }

    /**
     * Queue the auth marker cookie.
     */
    public static function queueMarker(): void
    {
        if ($name = static::cookieName()) {
            Cookie::queue($name, '1', static::COOKIE_MINUTES, ...static::pathAndDomain());
        }
    }

    /**
     * Queue a cookie that removes the auth marker.
     */
    public static function queueForget(): void
    {
        if ($name = static::cookieName()) {
            Cookie::queue(Cookie::forget($name, ...static::pathAndDomain()));
        }
    }

    /**
     * The name of the auth marker cookie, or null when the auth handling
     * is disabled.
     */
    public static function cookieName(): ?string
    {
        $name = config('static-vel.auth_cookie');

        return empty($name) ? null : (string) $name;
    }

    /**
     * The path and domain the marker cookie is managed with. Both the set
     * and the forget must target the exact same cookie, so they are taken
     * from the session configuration instead of relying on defaults.
     *
     * @return array{string, string|null}
     */
    protected static function pathAndDomain(): array
    {
        return [(string) config('session.path', '/'), config('session.domain')];
    }
}
