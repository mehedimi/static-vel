<?php

namespace Mehedi\StaticVel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

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

        if (! File::isDirectory($path)) {
            $this->info('No static pages to clear.');

            return self::SUCCESS;
        }

        $files = File::allFiles($path, hidden: true);

        if (! File::deleteDirectory($path, preserve: true)) {
            $this->error("Unable to clear the static pages from [{$path}].");

            return self::FAILURE;
        }

        $this->info('Cleared ['.count($files)."] static page(s) from [{$path}].");

        return self::SUCCESS;
    }
}
