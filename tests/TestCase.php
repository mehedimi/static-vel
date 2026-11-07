<?php

namespace Mehedimi\StaticVel\Tests;

use FilesystemIterator;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Testing\TestResponse;
use Mehedimi\StaticVel\StaticVelServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class TestCase extends TestbenchTestCase
{
    protected string $staticPath;

    protected function setUp(): void
    {
        $this->staticPath = sys_get_temp_dir().'/static-vel-tests/'.uniqid('', true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->staticPath);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [StaticVelServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('static-vel.path', $this->staticPath);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('static-vel')->group(function ($router) {
            $router->get('/', fn () => '<html>home</html>');
            $router->get('/about', fn () => '<html>about</html>');
            $router->post('/about', fn () => '<html>about</html>');
            $router->get('/products/{id}', fn (string $id) => "<html>product {$id}</html>");
            $router->get('/counter', function () {
                static $count = 0;

                return '<html>v'.(++$count).'</html>';
            });
            $router->get('/redirect', fn () => redirect('/'));
            $router->get('/json', fn () => response()->json(['ok' => true]));
            $router->get('/boom', fn () => throw new \RuntimeException('boom'));

            $router->get('/{any}', fn (string $any) => "<html>catch-all {$any}</html>")->where('any', '.*');
        });
    }

    /**
     * Request a page and let the middleware capture it, the same way the
     * HTTP kernel does after the response has been sent.
     */
    protected function visit(string $uri, array $headers = []): TestResponse
    {
        $response = $this->get($uri, $headers);

        $this->app->make(HttpKernel::class)->terminate(
            $this->app->make('request'),
            $response->baseResponse
        );

        return $response;
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($directory);
    }
}
