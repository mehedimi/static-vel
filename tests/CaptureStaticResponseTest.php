<?php

namespace Mehedi\StaticVel\Tests;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Mehedi\StaticVel\Capture;
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
    public function it_captures_query_string_variants_for_allowed_keys(): void
    {
        $this->visit('/blog?category=e-commerce');
        $this->visit('/blog?page=2');

        $this->assertFileExists($this->staticPath.'/blog.category=e-commerce.html');
        $this->assertFileExists($this->staticPath.'/blog.page=2.html');
        $this->assertSame('<html>blog</html>', file_get_contents($this->staticPath.'/blog.category=e-commerce.html'));
    }

    #[Test]
    public function it_captures_each_parameter_combination_as_its_own_file(): void
    {
        $this->visit('/blog?page=2&category=e-commerce');

        $this->assertFileExists($this->staticPath.'/blog.page=2&category=e-commerce.html');
        $this->assertFileDoesNotExist($this->staticPath.'/blog.html');
    }

    #[Test]
    public function it_does_not_capture_query_strings_by_default(): void
    {
        // The /about route never calls allowQueryKeys: query strings are
        // never cached there, whatever they contain.
        $this->visit('/about?category=e-commerce');
        $this->visit('/about?page=2');

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_query_strings_with_unallowed_parameters(): void
    {
        // One parameter outside the allowed keys is enough to skip the capture.
        $this->visit('/blog?category=shoes&madeup=value');
        $this->visit('/blog?madeup=1');

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_leak_allowed_query_keys_across_requests(): void
    {
        $this->visit('/blog?category=e-commerce');
        $this->visit('/about?category=e-commerce');

        $this->assertFileExists($this->staticPath.'/blog.category=e-commerce.html');
        $this->assertDirectoryDoesNotExist($this->staticPath.'/about.html');
    }

    #[Test]
    public function it_does_not_capture_unsafe_query_strings(): void
    {
        $this->visit('/blog?category=../../etc');

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_captures_inertia_visits_as_json(): void
    {
        $this->visit('/inertia-page', ['X-Inertia' => 'true']);

        $file = $this->staticPath.'/inertia-page.json';

        $this->assertFileExists($file);
        $this->assertStringContainsString('InertiaPage', (string) file_get_contents($file));
        $this->assertFileDoesNotExist($this->staticPath.'/inertia-page.html');
    }

    #[Test]
    public function it_captures_the_inertia_payload_of_the_root_page(): void
    {
        $this->visit('/', ['X-Inertia' => 'true']);

        $this->assertFileExists($this->staticPath.'/index.json');
    }

    #[Test]
    public function it_does_not_capture_inertia_visits_when_handling_is_disabled(): void
    {
        $this->app['config']->set('static-vel.handle_inertia', false);

        $this->visit('/inertia-page', ['X-Inertia' => 'true']);

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_inertia_partial_reloads(): void
    {
        $this->visit('/inertia-page', [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'InertiaPage',
        ]);

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_requests_carrying_the_auth_cookie(): void
    {
        $this->visit('/about', cookies: ['static_vel_auth' => '1']);

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_authenticated_users(): void
    {
        $this->actingAs($this->user());

        $this->visit('/about');

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
    public function it_does_not_capture_non_html_responses_outside_of_inertia(): void
    {
        $this->visit('/json');

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_capture_when_the_application_skips_the_response(): void
    {
        $this->visit('/skip');

        $this->assertDirectoryDoesNotExist($this->staticPath);
    }

    #[Test]
    public function it_does_not_leak_the_skip_flag_across_requests(): void
    {
        $this->visit('/skip');
        $this->visit('/about');

        $this->assertFileExists($this->staticPath.'/about.html');
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
