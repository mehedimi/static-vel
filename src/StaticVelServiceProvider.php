<?php

namespace Mehedi\StaticVel;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Mehedi\StaticVel\Auth\AuthCookieSubscriber;
use Mehedi\StaticVel\Commands\ClearStaticPages;
use Mehedi\StaticVel\Commands\ListStaticRoutes;
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
                ListStaticRoutes::class,
            ]);
        }

        if ($name = AuthCookieSubscriber::cookieName()) {
            $events->subscribe(AuthCookieSubscriber::class);

            // The marker only ever holds the constant "1", so there is no
            // secret to protect and nginx needs to see a plain value it can
            // act on. Exempt it from Laravel's cookie encryption.
            EncryptCookies::except($name);
        }

        $router->aliasMiddleware('static-vel', CaptureStaticResponse::class);
        $router->aliasMiddleware('static-vel.auth', SyncAuthCookie::class);
    }
}
