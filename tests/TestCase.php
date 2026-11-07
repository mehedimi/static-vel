<?php

namespace Mehedi\StaticVel\Tests;

use FilesystemIterator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Mehedi\StaticVel\CacheKey;
use Mehedi\StaticVel\StaticVelServiceProvider;
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
        // Uses the alias, so the provider's middleware registration is
        // covered too. Must be registered before the catch-all route.
        $router->middleware('static-vel.auth')->get('/sync', fn () => 'ok');

        $router->middleware('static-vel')->group(function ($router) {
            // Routes mimic an Inertia app: X-Inertia visits get the JSON
            // payload, full page loads get the HTML shell.
            $router->get('/', function (Request $request) {
                return $request->hasHeader('X-Inertia')
                    ? response()->json(['component' => 'Home', 'props' => [], 'url' => '/', 'version' => '1'])
                    : '<html>home</html>';
            });
            $router->get('/inertia-page', function (Request $request) {
                return $request->hasHeader('X-Inertia')
                    ? response()->json(['component' => 'InertiaPage', 'props' => ['title' => 'Hello'], 'url' => '/inertia-page', 'version' => '1'])
                    : '<html>inertia page</html>';
            });

            $router->get('/about', fn () => '<html>about</html>');
            $router->post('/about', fn () => '<html>about</html>');
            $router->get('/blog', function (Request $request) {
                // Simulates a controller that validates its query parameters
                // and opts the validated keys into the cache key.
                CacheKey::allowQueryKeys(['category', 'page']);

                return '<html>blog</html>';
            });
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
    protected function visit(string $uri, array $headers = [], array $cookies = []): TestResponse
    {
        $response = $this->call('GET', $uri, [], $cookies, [], $this->transformHeadersToServerVars($headers));

        $this->app->make(HttpKernel::class)->terminate(
            $this->app->make('request'),
            $response->baseResponse
        );

        return $response;
    }

    /**
     * A minimal authenticatable user.
     */
    protected function user(): Authenticatable
    {
        return new class implements Authenticatable
        {
            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 1;
            }

            public function getAuthPassword()
            {
                return 'secret';
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getRememberToken()
            {
                return '';
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        };
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
