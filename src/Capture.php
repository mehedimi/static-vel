<?php

namespace Mehedi\StaticVel;

use Illuminate\Http\Request;

use function app;

class Capture
{
    /**
     * The request attribute the skip flag is stored under. State lives on
     * the request itself, so it is scoped to exactly one request and can
     * never leak between Octane / PHP-PM worker requests.
     */
    protected const string SKIP_ATTRIBUTE = 'static_vel_skip_capture';

    /**
     * Tell the package to skip capturing this response. Call it anywhere
     * while handling the request (controller, closure, middleware):
     *
     *   Capture::skip();
     *
     * The response is still rendered and sent to the visitor - only the
     * static capture is skipped, and the next request will hit the
     * application again.
     */
    public static function skip(): void
    {
        if (! app()->bound('request')) {
            return;
        }

        app(Request::class)->attributes->set(self::SKIP_ATTRIBUTE, true);
    }

    /**
     * Whether the application asked to skip the capture for this request.
     */
    public static function skipped(Request $request): bool
    {
        return (bool) $request->attributes->get(self::SKIP_ATTRIBUTE, false);
    }
}
