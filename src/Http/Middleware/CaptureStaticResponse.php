<?php

namespace Mehedi\StaticVel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Mehedi\StaticVel\Auth\AuthCookieSubscriber;
use Mehedi\StaticVel\Support\StaticPagePath;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function auth;
use function config;
use function logger;

class CaptureStaticResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Capture the response after it has been sent to the browser, so the
     * page can be served as a static file next time.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            if ($this->shouldCapture($request, $response)) {
                $this->store($request, $response);
            }
        } catch (Throwable $exception) {
            logger()->warning('static-vel: failed to capture the response.', [
                'path' => $request->path(),
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Determine whether the response should be written to the static cache.
     */
    protected function shouldCapture(Request $request, Response $response): bool
    {
        if (! config('static-vel.enabled', true)) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // Authenticated visitors always receive fresh content from Laravel -
        // the auth marker cookie is exactly what nginx checks. Their
        // responses must never overwrite the guest cache.
        if ($name = AuthCookieSubscriber::cookieName()) {
            if ($request->hasCookie($name) || auth()->guard()->check()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Write the response body to the static cache, atomically.
     */
    protected function store(Request $request, Response $response): void
    {
        // Inertia partial reloads return only a subset of the page props -
        // never cache them, they would overwrite the full payload.
        if ($request->hasHeader('X-Inertia-Partial-Component')) {
            return;
        }

        if ($request->hasHeader('X-Inertia')) {
            if (! config('static-vel.handle_inertia', true)) {
                return;
            }

            if (! $this->hasContentType($response, 'application/json')) {
                return;
            }

            $target = StaticPagePath::forRequest($request, 'json');
        } else {
            if (! $this->hasContentType($response, 'text/html')) {
                return;
            }

            $target = StaticPagePath::forRequest($request, 'html');
        }

        if ($target === null) {
            return;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        $this->write($target, $content);
    }

    /**
     * Determine whether the response's content type starts with the given type.
     */
    protected function hasContentType(Response $response, string $type): bool
    {
        return str_starts_with(strtolower((string) $response->headers->get('Content-Type')), $type);
    }

    /**
     * Write the content to the target file, atomically.
     */
    protected function write(string $target, string $content): void
    {
        $directory = dirname($target);

        File::ensureDirectoryExists($directory, 0775);

        if (! File::isDirectory($directory)) {
            logger()->warning("static-vel: unable to create the directory [{$directory}].");

            return;
        }

        // Write to a temporary file first and rename it, so nginx never
        // picks up a half-written page.
        $temporary = $directory.DIRECTORY_SEPARATOR.basename($target).'.'.uniqid('', true).'.tmp';

        if (File::put($temporary, $content) === false) {
            logger()->warning("static-vel: unable to write the file [{$temporary}].");

            return;
        }

        if (! File::move($temporary, $target)) {
            File::delete($temporary);

            logger()->warning("static-vel: unable to finalize the file [{$target}].");
        }
    }
}
