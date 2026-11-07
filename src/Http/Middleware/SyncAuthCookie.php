<?php

namespace Mehedi\StaticVel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mehedi\StaticVel\Auth\AuthCookieSubscriber;
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
    public function handle(Request $request, Closure $next): Response
    {
        $name = AuthCookieSubscriber::cookieName();

        if ($name) {
            $authenticated = auth()->guard()->check();

            if ($authenticated && ! $request->hasCookie($name)) {
                AuthCookieSubscriber::queueMarker();
            } elseif (! $authenticated && $request->hasCookie($name)) {
                AuthCookieSubscriber::queueForget();
            }
        }

        return $next($request);
    }
}
