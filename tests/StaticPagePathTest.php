<?php

namespace Mehedi\StaticVel\Tests;

use Illuminate\Http\Request;
use Mehedi\StaticVel\Support\StaticPagePath;
use PHPUnit\Framework\Attributes\Test;

class StaticPagePathTest extends TestCase
{
    #[Test]
    public function it_maps_the_root_page_to_index(): void
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
    public function it_maps_query_strings_into_the_file_name(): void
    {
        $this->assertSame(
            $this->staticPath.'/blog.category=e-commerce.html',
            StaticPagePath::forRequest(Request::create('/blog?category=e-commerce'))
        );
    }

    #[Test]
    public function it_maps_the_root_page_with_a_query_string(): void
    {
        $this->assertSame(
            $this->staticPath.'/index.page=2.json',
            StaticPagePath::forRequest(Request::create('/?page=2'), 'json')
        );
    }

    #[Test]
    public function it_keeps_multiple_query_parameters_verbatim(): void
    {
        $this->assertSame(
            $this->staticPath.'/products.type=shoes&color=red&size=42.html',
            StaticPagePath::forRequest(Request::create('/products?type=shoes&color=red&size=42'))
        );
    }

    #[Test]
    public function it_treats_different_parameter_orders_as_different_files(): void
    {
        $this->assertSame(
            $this->staticPath.'/blog.a=1&b=2.html',
            StaticPagePath::forRequest(Request::create('/blog?a=1&b=2'))
        );
        $this->assertSame(
            $this->staticPath.'/blog.b=2&a=1.html',
            StaticPagePath::forRequest(Request::create('/blog?b=2&a=1'))
        );
    }

    #[Test]
    public function it_rejects_unsafe_query_strings(): void
    {
        $this->assertNull(StaticPagePath::forRequest(Request::create('/blog?category=../../etc')));
        $this->assertNull(StaticPagePath::forRequest(Request::create('/blog?category=..%2F..%2Fetc')));
        $this->assertNull(StaticPagePath::forRequest(Request::create('/blog?x='.str_repeat('a', 200))));
    }

    #[Test]
    public function it_accepts_a_custom_extension(): void
    {
        $this->assertSame(
            $this->staticPath.'/about.json',
            StaticPagePath::forRequest(Request::create('/about'), 'json')
        );
        $this->assertSame(
            $this->staticPath.'/index.json',
            StaticPagePath::forRequest(Request::create('/'), 'json')
        );
    }

    #[Test]
    public function it_rejects_path_traversal_attempts(): void
    {
        $this->assertNull(StaticPagePath::forRequest(Request::create('/..%2Fetc%2Fpasswd')));
        $this->assertNull(StaticPagePath::forRequest(Request::create('/..%5C..%2Fsecret')));
    }
}
