<?php

namespace Mehedi\StaticVel\Tests;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
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
            ->expectsOutputToContain('Static pages cleared.')
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

    #[Test]
    public function it_fails_when_the_files_cannot_be_deleted(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('Root can delete files regardless of permissions.');
        }

        File::ensureDirectoryExists($this->staticPath, 0775);

        file_put_contents($this->staticPath.'/about.html', 'about');

        // The captured pages are owned by the PHP user on a real server -
        // a read-only directory reproduces what the deploy user sees.
        chmod($this->staticPath, 0555);

        try {
            $this->artisan('static-vel:clear')
                ->expectsOutputToContain('Unable to clear the static pages')
                ->assertExitCode(Command::FAILURE);

            $this->assertFileExists($this->staticPath.'/about.html');
        } finally {
            chmod($this->staticPath, 0775);
        }
    }
}
