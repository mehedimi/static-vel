<?php

namespace Mehedimi\StaticVel\Tests;

use Illuminate\Support\Facades\Cookie;
use PHPUnit\Framework\Attributes\Test;

class SyncAuthCookieTest extends TestCase
{
    #[Test]
    public function it_queues_the_marker_cookie_for_authenticated_visitors_without_one(): void
    {
        $this->actingAs($this->user());

        $this->get('/sync');

        $cookie = $this->queuedCookie('static_vel_auth');

        $this->assertNotNull($cookie);
        $this->assertFalse($cookie->isCleared());
    }

    #[Test]
    public function it_queues_a_forget_cookie_for_guests_carrying_the_marker(): void
    {
        $this->withUnencryptedCookies(['static_vel_auth' => '1'])->get('/sync');

        $cookie = $this->queuedCookie('static_vel_auth');

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isCleared());
    }

    #[Test]
    public function it_queues_nothing_when_the_cookie_is_already_in_sync(): void
    {
        $this->actingAs($this->user());
        $this->withUnencryptedCookies(['static_vel_auth' => '1'])->get('/sync');

        $this->assertSame([], Cookie::getQueuedCookies());
    }

    #[Test]
    public function it_does_nothing_when_the_auth_cookie_is_disabled(): void
    {
        $this->app['config']->set('static-vel.auth_cookie', null);

        $this->actingAs($this->user());
        $this->get('/sync');

        $this->assertSame([], Cookie::getQueuedCookies());
    }

    #[Test]
    public function it_does_nothing_for_guests_without_the_marker(): void
    {
        $this->get('/sync');

        $this->assertSame([], Cookie::getQueuedCookies());
    }

    /**
     * The queued cookie with the given name.
     */
    protected function queuedCookie(string $name): ?\Symfony\Component\HttpFoundation\Cookie
    {
        return collect(Cookie::getQueuedCookies())
            ->first(fn (\Symfony\Component\HttpFoundation\Cookie $cookie) => $cookie->getName() === $name);
    }
}
