<?php

namespace Mehedi\StaticVel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Mehedi\StaticVel\Support\StaticPagePath;
use SplFileInfo;

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
        $path = StaticPagePath::baseDirectory();

        if (! File::isDirectory($path)) {
            $this->info('No static pages to clear.');

            return self::SUCCESS;
        }

        // File::deleteDirectory() always reports success, even when every
        // unlink fails (e.g. the files are owned by the PHP user while the
        // command runs as someone else). Delete the files directly and
        // verify the directory is actually empty afterwards.
        $paths = array_map(fn (SplFileInfo $file) => $file->getPathname(), File::allFiles($path, hidden: true));

        if ($paths !== []) {
            File::delete($paths);
        }

        // Remove the emptied nested directories, keeping the directory itself.
        File::deleteDirectory($path, preserve: true);

        if (File::allFiles($path, hidden: true) !== []) {
            $this->error("Unable to clear the static pages from [{$path}]. Make the directory writable for the user running this command.");

            return self::FAILURE;
        }

        $this->info('Static pages cleared.');

        return self::SUCCESS;
    }
}
