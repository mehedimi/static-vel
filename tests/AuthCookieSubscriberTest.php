<?php

namespace Mehedi\StaticVel\Tests;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Mehedi\StaticVel\Auth\AuthCookieSubscriber;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

class AuthCookieSubscriberTest extends TestCase
{
    #[Test]
    public function it_queues_the_marker_cookie_on_login(): void
    {
        event(new Login('web', $this->user(), false));

        $cookie = collect(Cookie::getQueuedCookies())
            ->first(fn (\Symfony\Component\HttpFoundation\Cookie $cookie) => $cookie->getName() === 'static_vel_auth');

        $this->assertNotNull($cookie);
        $this->assertFalse($cookie->isCleared());
        $this->assertSame('1', $cookie->getValue());
    }

    #[Test]
    public function it_queues_a_forget_cookie_on_logout(): void
    {
        event(new Logout('web', $this->user()));

        $cookie = collect(Cookie::getQueuedCookies())
            ->first(fn (\Symfony\Component\HttpFoundation\Cookie $cookie) => $cookie->getName() === 'static_vel_auth');

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isCleared());
    }

    #[Test]
    public function it_does_nothing_when_the_auth_cookie_is_disabled(): void
    {
        $this->app['config']->set('static-vel.auth_cookie', null);

        event(new Login('web', $this->user(), false));
        event(new Logout('web', $this->user()));

        $this->assertSame([], Cookie::getQueuedCookies());
    }

    #[Test]
    public function it_exempts_the_marker_cookie_from_encryption_on_the_response(): void
    {
        $middleware = new EncryptCookies($this->app->make(Encrypter::class));

        $response = new Response;
        $response->headers->setCookie(Cookie::make('static_vel_auth', '1', AuthCookieSubscriber::COOKIE_MINUTES));
        $response->headers->setCookie(Cookie::make('control', 'encrypted'));

        $result = $middleware->handle(Request::create('/'), fn () => $response);

        $cookies = collect($result->headers->getCookies())
            ->mapWithKeys(fn (\Symfony\Component\HttpFoundation\Cookie $cookie) => [$cookie->getName() => $cookie->getValue()]);

        $this->assertSame('1', $cookies['static_vel_auth']);
        $this->assertStringStartsWith('eyJpdiI6', $cookies['control']);
    }

    #[Test]
    public function it_keeps_the_plain_marker_cookie_on_the_request(): void
    {
        $middleware = new EncryptCookies($this->app->make(Encrypter::class));

        $captured = null;

        $middleware->handle(
            Request::create('/', 'GET', [], ['static_vel_auth' => '1']),
            function (Request $request) use (&$captured): Response {
                $captured = $request;

                return new Response;
            }
        );

        // Without the exemption the middleware nulls the value out because
        // the plain "1" cannot be decrypted.
        $this->assertSame('1', $captured->cookies->get('static_vel_auth'));
    }
}
