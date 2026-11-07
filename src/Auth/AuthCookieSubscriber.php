<?php

namespace Mehedi\StaticVel\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cookie;

use function config;

class AuthCookieSubscriber
{
    /**
     * The lifetime of the marker cookie, in minutes.
     */
    public const COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher  $events
     * @return array<class-string, string>
     */
    public function subscribe($events): array
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
        if (($name = static::cookieName()) !== null) {
            Cookie::queue($name, '1', static::COOKIE_MINUTES);
        }
    }

    /**
     * Remove the marker after a logout.
     */
    public function handleLogout(Logout $event): void
    {
        if (($name = static::cookieName()) !== null) {
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

        return empty($name) ? null : (string) $name;
    }
}
