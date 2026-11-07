<?php

namespace Mehedimi\StaticVel\Tests;

use Illuminate\Http\Request;
use Mehedimi\StaticVel\Support\StaticPagePath;
use PHPUnit\Framework\Attributes\Test;

class StaticPagePathTest extends TestCase
{
    #[Test]
    public function it_maps_the_root_page_to_index_html(): void
    {
        $this->assertSame(
            $this->staticPath.'/index.html',
            StaticPagePath::forRequest(Request::create('/'))
        );
    }

    #[Test]
    public function it_maps_a_simple_path_to_an_html_file(): void
    {
        $this->assertSame(
            $this->staticPath.'/about.html',
            StaticPagePath::forRequest(Request::create('/about'))
        );
    }

    #[Test]
    public function it_maps_a_nested_path_to_an_html_file(): void
    {
        $this->assertSame(
            $this->staticPath.'/products/12.html',
            StaticPagePath::forRequest(Request::create('/products/12'))
        );
    }

    #[Test]
    public function it_treats_a_trailing_slash_like_the_clean_path(): void
    {
        $this->assertSame(
            $this->staticPath.'/about.html',
            StaticPagePath::forRequest(Request::create('/about/'))
        );
    }

    #[Test]
    public function it_allows_requests_carrying_only_ignored_query_parameters(): void
    {
        $this->assertSame(
            $this->staticPath.'/about.html',
            StaticPagePath::forRequest(Request::create('/about?utm_source=twitter&fbclid=abc123'))
        );
    }

    #[Test]
    public function it_rejects_requests_with_other_query_parameters(): void
    {
        $this->assertNull(StaticPagePath::forRequest(Request::create('/about?page=2&sort=desc')));
    }

    #[Test]
    public function it_rejects_path_traversal_attempts(): void
    {
        $this->assertNull(StaticPagePath::forRequest(Request::create('/..%2Fetc%2Fpasswd')));
        $this->assertNull(StaticPagePath::forRequest(Request::create('/..%5C..%2Fsecret')));
    }
}
