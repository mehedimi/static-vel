<?php

namespace Mehedimi\StaticVel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mehedimi\StaticVel\Support\StaticPagePath;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function config;
use function logger;

class CaptureStaticResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Capture the response after it has been sent to the browser, so the
     * page can be served as a static file next time.
     *
     * @param  Request  $request
     * @param  Response  $response
     */
    public function terminate($request, $response): void
    {
        try {
            if ($this->shouldCapture($request, $response)) {
                $this->store($request, $response);
            }
        } catch (\Throwable $exception) {
            logger()->warning('static-vel: failed to capture the response.', [
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Determine whether the response should be written to the static cache.
     */
    protected function shouldCapture($request, $response): bool
    {
        if (! config('static-vel.enabled', true)) {
            return false;
        }

        if (! $request instanceof Request || ! $response instanceof Response) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        if (! in_array($response->getStatusCode(), (array) config('static-vel.cacheable_status_codes', [200]), true)) {
            return false;
        }

        if (! $this->hasCacheableContentType($response)) {
            return false;
        }

        // Inertia visits expect a JSON payload from Laravel: they drive the
        // asset version check (409 + full reload) and partial reloads, so
        // they must never be answered with a static HTML file.
        if ($request->hasHeader('X-Inertia')) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the response's content type should be captured.
     */
    protected function hasCacheableContentType(Response $response): bool
    {
        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        return collect((array) config('static-vel.cacheable_content_types', ['text/html']))
            ->contains(fn (string $type) => str_starts_with($contentType, strtolower($type)));
    }

    /**
     * Write the response body to the static cache, atomically.
     */
    protected function store(Request $request, Response $response): void
    {
        $target = StaticPagePath::forRequest($request);

        if ($target === null) {
            return;
        }

        $content = $response->getContent();

        if ($content === false || $content === null || $content === '') {
            return;
        }

        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            logger()->warning("static-vel: unable to create the directory [{$directory}].");

            return;
        }

        // Write to a temporary file first and rename it, so nginx never
        // picks up a half-written page.
        $temporary = $directory.DIRECTORY_SEPARATOR.basename($target).'.'.uniqid('', true).'.tmp';

        if (@file_put_contents($temporary, $content) === false) {
            logger()->warning("static-vel: unable to write the file [{$temporary}].");

            return;
        }

        if (! @rename($temporary, $target)) {
            @unlink($temporary);

            logger()->warning("static-vel: unable to finalize the file [{$target}].");
        }
    }
}
