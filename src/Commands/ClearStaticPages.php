<?php

namespace Mehedi\StaticVel\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function config;

class ClearStaticPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'static-vel:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all captured static pages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = rtrim((string) config('static-vel.path'), '/\\');

        if (! is_dir($path)) {
            $this->info('No static pages to clear.');

            return self::SUCCESS;
        }

        $files = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());

                continue;
            }

            if (@unlink($file->getPathname())) {
                $files++;
            }
        }

        $this->info("Cleared [{$files}] static page(s) from [{$path}].");

        return self::SUCCESS;
    }
}
