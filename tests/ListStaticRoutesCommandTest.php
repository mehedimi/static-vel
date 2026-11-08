<?php

namespace Mehedi\StaticVel\Tests;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Artisan;
use Mehedi\StaticVel\Http\Middleware\CaptureStaticResponse;
use PHPUnit\Framework\Attributes\Test;

class ListStaticRoutesCommandTest extends TestCase
{
    #[Test]
    public function it_lists_the_routes_covered_by_static_rendering(): void
    {
        $output = $this->listRoutes();

        $this->assertStringContainsString('/about', $output);
        $this->assertStringContainsString('about.html', $output);
        $this->assertStringContainsString('index.html', $output);
        $this->assertStringContainsString('products/{id}.html', $output);
    }

    #[Test]
    public function it_does_not_list_routes_without_the_capture_middleware(): void
    {
        // /sync only carries the static-vel.auth middleware, never the capture.
        $this->assertStringNotContainsString('sync', $this->listRoutes());
    }

    #[Test]
    public function it_lists_the_inertia_json_file_next_to_the_html_file(): void
    {
        $this->assertStringContainsString('about.html, about.json', $this->listRoutes());
    }

    #[Test]
    public function it_does_not_list_the_inertia_json_file_when_handling_is_disabled(): void
    {
        $this->app['config']->set('static-vel.handle_inertia', false);

        $output = $this->listRoutes();

        $this->assertStringContainsString('about.html', $output);
        $this->assertStringNotContainsString('about.json', $output);
    }

    #[Test]
    public function it_lists_routes_in_a_middleware_group_containing_the_capture_middleware(): void
    {
        $router = $this->app['router'];

        $router->middlewareGroup('cached', [CaptureStaticResponse::class]);
        $router->get('/grouped', fn () => '<html>grouped</html>')->middleware('cached');

        $this->assertStringContainsString('grouped.html', $this->listRoutes());
    }

    #[Test]
    public function it_lists_routes_using_a_custom_alias_of_the_capture_middleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('my-cache', CaptureStaticResponse::class);
        $router->get('/aliased', fn () => '<html>aliased</html>')->middleware('my-cache');

        $this->assertStringContainsString('aliased.html', $this->listRoutes());
    }

    #[Test]
    public function it_lists_every_route_when_the_middleware_is_registered_globally(): void
    {
        $this->app->make(HttpKernel::class)->pushMiddleware(CaptureStaticResponse::class);

        $output = $this->listRoutes();

        $this->assertStringContainsString('registered globally', $output);
        // /sync is not covered through its route middleware, only by the
        // global registration.
        $this->assertStringContainsString('/sync', $output);
    }

    #[Test]
    public function it_reports_when_no_routes_are_covered(): void
    {
        $this->app['router']->setRoutes(new RouteCollection);

        $output = $this->listRoutes();

        $this->assertStringContainsString('No routes are covered by static rendering', $output);
    }

    protected function listRoutes(): string
    {
        $this->assertSame(0, Artisan::call('static-vel:routes'));

        return Artisan::output();
    }
}
