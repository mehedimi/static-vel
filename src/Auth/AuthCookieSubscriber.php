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
            Cookie::queue($name, '1', static::COOKIE_MINUTES);
        }
    }

    /**
     * Queue a cookie that removes the auth marker. Both the queue and the
     * forget fall back to the cookie jar defaults (session.path and
     * session.domain), so they always target the same cookie.
     */
    public static function queueForget(): void
    {
        if ($name = static::cookieName()) {
            Cookie::queue(Cookie::forget($name));
        }
    }

    /**
     * The name of the auth marker cookie, or null when the auth handling
     * is disabled.
     */
    public static function cookieName(): ?string
    {
        $name = config('static-vel.auth_cookie');

        if (! is_string($name) || $name === '') {
            return null;
        }

        return $name;
    }
}
