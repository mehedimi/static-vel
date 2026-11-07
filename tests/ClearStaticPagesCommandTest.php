<?php

namespace Mehedi\StaticVel\Tests;

use PHPUnit\Framework\Attributes\Test;

class ClearStaticPagesCommandTest extends TestCase
{
    #[Test]
    public function it_clears_all_captured_pages_but_keeps_the_directory(): void
    {
        mkdir($this->staticPath.'/products', 0777, true);
        file_put_contents($this->staticPath.'/index.html', 'home');
        file_put_contents($this->staticPath.'/products/12.html', 'product');

        $this->artisan('static-vel:clear')
            ->expectsOutputToContain('Cleared [2] static page(s)')
            ->assertSuccessful();

        $this->assertDirectoryExists($this->staticPath);
        $this->assertFileDoesNotExist($this->staticPath.'/index.html');
        $this->assertFileDoesNotExist($this->staticPath.'/products/12.html');
        $this->assertDirectoryDoesNotExist($this->staticPath.'/products');
    }

    #[Test]
    public function it_handles_a_missing_directory(): void
    {
        $this->artisan('static-vel:clear')
            ->expectsOutputToContain('No static pages to clear.')
            ->assertSuccessful();
    }
}
