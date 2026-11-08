<?php

namespace Mehedi\StaticVel\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Mehedi\StaticVel\Http\Middleware\CaptureStaticResponse;
use Mehedi\StaticVel\Support\StaticPagePath;

class ListStaticRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'static-vel:routes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the routes covered by static rendering';

    /**
     * Execute the console command.
     */
    public function handle(Router $router): int
    {
        /** @var array<string, list<mixed>> $groups */
        $groups = $router->getMiddlewareGroups();

        /** @var array<string, string> $aliases */
        $aliases = $router->getMiddleware();

        $global = $this->isRegisteredGlobally($groups, $aliases);

        $routes = $router->getRoutes()->getRoutes();

        usort($routes, fn (Route $a, Route $b) => $a->uri() <=> $b->uri());

        $rows = [];

        foreach ($routes as $route) {
            // Only GET responses are ever captured, so POST/PUT-only routes
            // are not covered no matter which middleware they carry.
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! $global && ! $this->coversCapture($route, $groups, $aliases)) {
                continue;
            }

            $rows[] = $this->rowFor($route);
        }

        if ($rows === []) {
            $this->info('No routes are covered by static rendering. Attach the [static-vel] middleware to the routes you want to capture.');

            return self::SUCCESS;
        }

        if ($global) {
            $this->info('The capture middleware is registered globally, so every GET route is covered.');
        }

        $this->table(['Method', 'URI', 'Static file'], $rows);

        $this->info('Captured pages are stored in ['.StaticPagePath::baseDirectory().'], served to guests on 200 GET responses.');
        $this->info('Query strings are only cached for keys allowed via CacheKey::allowQueryKeys().');

        return self::SUCCESS;
    }

    /**
     * Determine whether the capture middleware is registered globally, in
     * which case every route is covered.
     *
     * @param  array<string, list<mixed>>  $groups
     * @param  array<string, string>  $aliases
     */
    protected function isRegisteredGlobally(array $groups, array $aliases): bool
    {
        $kernel = $this->laravel->make(HttpKernel::class);

        if (! method_exists($kernel, 'getGlobalMiddleware')) {
            return false;
        }

        /** @var list<mixed> $global */
        $global = $kernel->getGlobalMiddleware();

        foreach ($global as $middleware) {
            if ($this->isCaptureMiddleware($middleware, $groups, $aliases)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the route carries the capture middleware, directly
     * or through a middleware group.
     *
     * @param  array<string, list<mixed>>  $groups
     * @param  array<string, string>  $aliases
     */
    protected function coversCapture(Route $route, array $groups, array $aliases): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if ($this->isCaptureMiddleware($middleware, $groups, $aliases)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether a middleware entry is the capture middleware.
     *
     * @param  array<string, list<mixed>>  $groups
     * @param  array<string, string>  $aliases
     * @param  array<string, true>  $seen
     */
    protected function isCaptureMiddleware(mixed $middleware, array $groups, array $aliases, array $seen = []): bool
    {
        if ($middleware instanceof CaptureStaticResponse) {
            return true;
        }

        if (! is_string($middleware)) {
            return false;
        }

        // Parameters on the middleware do not change its identity.
        $name = Str::before($middleware, ':');

        if ($name === 'static-vel' || $name === CaptureStaticResponse::class) {
            return true;
        }

        // A custom alias pointing at the capture middleware.
        if (($aliases[$name] ?? null) === CaptureStaticResponse::class) {
            return true;
        }

        // A middleware group that contains the capture middleware. The seen
        // map guards against groups referencing each other in a loop.
        if (isset($seen[$name]) || ! is_array($groups[$name] ?? null)) {
            return false;
        }

        $seen[$name] = true;

        foreach ($groups[$name] as $member) {
            if ($this->isCaptureMiddleware($member, $groups, $aliases, $seen)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The table row for a route: its methods, URI and the static file name
     * the capture writes, resolved with the exact same path mapping the
     * middleware uses.
     *
     * @return array{string, string, string}
     */
    protected function rowFor(Route $route): array
    {
        /** @var list<string> $routeMethods */
        $routeMethods = $route->methods();

        $methods = array_diff($routeMethods, ['HEAD']);

        return [implode('|', $methods), '/'.ltrim($route->uri(), '/'), $this->staticFile($route)];
    }

    /**
     * The captured file name for a route, relative to the storage directory.
     */
    protected function staticFile(Route $route): string
    {
        $request = Request::create('/'.ltrim($route->uri(), '/'));

        $file = $this->relativeFile($request, 'html');

        if (config('static-vel.handle_inertia', true)) {
            $file .= ', '.$this->relativeFile($request, 'json');
        }

        return $file;
    }

    /**
     * The captured file for the request and extension, relative to the
     * storage directory.
     */
    protected function relativeFile(Request $request, string $extension): string
    {
        $target = StaticPagePath::forRequest($request, $extension);

        if ($target === null) {
            return '-';
        }

        $base = StaticPagePath::baseDirectory().'/';

        return str_starts_with($target, $base) ? substr($target, strlen($base)) : $target;
    }
}
