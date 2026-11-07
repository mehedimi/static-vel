<?php

namespace Mehedimi\StaticVel\Tests;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use PHPUnit\Framework\Attributes\Test;

class CaptureStaticResponseTest extends TestCase
{
    #[Test]
    public function it_captures_the_root_page_as_index_html(): void
    {
        $this->visit('/');

        $this->assertFileExists($this->staticPath.'/index.html');
        $this->assertSame('<html>home</html>', file_get_contents($this->staticPath.'/index.html'));
    }

    #[Test]
    public function it_captures_a_page_by_its_path(): void
    {
        $this->visit('/about');

        $this->assertFileExists($this->staticPath.'/about.html');
        $this->assertSame('<html>about</html>', file_get_contents($this->staticPath.'/about.html'));
    }

    #[Test]
    public function it_captures_nested_pages(): void
    {
        $this->visit('/products/12');

        $this->assertFileExists($this->staticPath.'/products/12.html');
        $this->assertSame('<html>product 12</html>', file_get_contents($this->staticPath.'/products/12.html'));
    }

    #[Test]
    public function it_overwrites_a_previously_captured_page(): void
    {
        $this->visit('/counter');
        $this->visit('/counter');

        $this->assertSame('<html>v2</html>', file_get_contents($this->staticPath.'/counter.html'));
    }

    #[Test]
    public function it_captures_requests_carrying_only_ignored_query_parameters(): void
    {
        $this->visit('/about?utm_source=twitter&utm_campaign=launch');

        $this->assertFileExists($this->staticPath.'/about.html');
    }

    #[Test]
    public function it_does_not_capture_requests_with_other_query_parameters(): void
    {
        $this->visit('/about?page=2');

        $this->assertFileDoesNotExist($this->staticPath.'/about.html');
        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_non_get_requests(): void
    {
        $response = $this->post('/about');

        $this->app->make(HttpKernel::class)->terminate(
            $this->app->make('request'),
            $response->baseResponse
        );

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_redirects(): void
    {
        $this->visit('/redirect');

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_error_responses(): void
    {
        $this->visit('/boom');

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_non_html_responses(): void
    {
        $this->visit('/json');

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_inertia_visits(): void
    {
        $this->visit('/about', ['X-Inertia' => 'true']);

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_when_disabled(): void
    {
        $this->app['config']->set('static-vel.enabled', false);

        $this->visit('/about');

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_rejects_path_traversal_attempts(): void
    {
        $this->visit('/..%2Fetc%2Fpasswd');

        $this->assertDirectoryDoesNotExist($this->staticPath);
        $this->assertDirectoryDoesNotExist(dirname($this->staticPath).'/etc');
    }
}
