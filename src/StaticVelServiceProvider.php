<?php

namespace Mehedi\StaticVel;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Mehedi\StaticVel\Auth\AuthCookieSubscriber;
use Mehedi\StaticVel\Commands\ClearStaticPages;
use Mehedi\StaticVel\Http\Middleware\CaptureStaticResponse;
use Mehedi\StaticVel\Http\Middleware\SyncAuthCookie;

class StaticVelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/static-vel.php', 'static-vel');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $events, Router $router): void
    {
        $this->publishes([
            __DIR__.'/../config/static-vel.php' => config_path('static-vel.php'),
        ], 'static-vel-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearStaticPages::class,
            ]);
        }

        if (AuthCookieSubscriber::cookieName()) {
            $events->subscribe(AuthCookieSubscriber::class);
        }

        $router->aliasMiddleware('static-vel', CaptureStaticResponse::class);
        $router->aliasMiddleware('static-vel.auth', SyncAuthCookie::class);
    }
}
