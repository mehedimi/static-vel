<?php

namespace Mehedimi\StaticVel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Mehedimi\StaticVel\Auth\AuthCookieSubscriber;
use Symfony\Component\HttpFoundation\Response;

use function auth;

class SyncAuthCookie
{
    /**
     * Keep the auth marker cookie in sync with the real authentication
     * state on every request.
     *
     * The login / logout events already maintain the cookie; this middleware
     * additionally fixes visitors whose session predates the package (or
     * drifted away without a logout event), so nginx can always rely on the
     * cookie being present exactly when the visitor is authenticated.
     *
     * Place it in the `web` middleware group.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $name = AuthCookieSubscriber::cookieName();

        if ($name !== null) {
            $authenticated = auth()->check();

            if ($authenticated && ! $request->hasCookie($name)) {
                Cookie::queue($name, '1', AuthCookieSubscriber::COOKIE_MINUTES);
            } elseif (! $authenticated && $request->hasCookie($name)) {
                Cookie::queue(Cookie::forget($name));
            }
        }

        return $next($request);
    }
}
