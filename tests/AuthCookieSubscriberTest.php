<?php

namespace Mehedimi\StaticVel\Tests;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cookie;
use PHPUnit\Framework\Attributes\Test;

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
}
