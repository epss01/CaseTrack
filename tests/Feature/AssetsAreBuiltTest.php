<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * The compiled front-end assets are present and not older than their source.
 *
 * public/build is gitignored, so compiled CSS and JS exist only on machines
 * that have built them. A *missing* build already fails loudly — Laravel's
 * @vite throws "Vite manifest not found" on the first page load. A *stale*
 * build does not: the page renders with the previous build's CSS, so a styling
 * change that landed in the branch simply does not appear, and nothing says
 * why. That is the failure this test exists to catch.
 *
 * It lives in the PHPUnit suite rather than in a git hook or a separate script
 * because `php artisan test` is the one check this project already runs by
 * habit (CLAUDE.md, Workflow notes) — a hook would have to be installed on
 * every machine to work, and .git/hooks is not committed. When a CI pipeline
 * eventually exists, this comes along with it for free.
 */
class AssetsAreBuiltTest extends TestCase
{
    /**
     * Only these are compiled. Blade, PHP and routes are read at request time
     * and never need a rebuild — which is worth stating, because "I changed a
     * view, do I need to build?" is the question this answers.
     *
     * @var list<string>
     */
    private const COMPILED_FROM = ['sass', 'js'];

    public function test_the_compiled_assets_are_present_and_no_older_than_their_source(): void
    {
        // `npm run dev` serves assets from memory and writes public/hot instead
        // of public/build — the same file Laravel itself checks to decide where
        // to point @vite. There is nothing on disk to be stale.
        if (file_exists(public_path('hot'))) {
            $this->markTestSkipped('The Vite dev server is running; assets are served from memory.');
        }

        $manifest = public_path('build/manifest.json');

        $this->assertFileExists($manifest, implode("\n", [
            'The front-end assets have never been built on this machine.',
            'public/build is gitignored, so a fresh clone has no compiled CSS or JS.',
            '',
            '    npm install && npm run build',
        ]));

        [$newest, $newestFile] = $this->newestSourceFile();

        $this->assertGreaterThanOrEqual($newest, filemtime($manifest), implode("\n", [
            'The compiled assets are older than their source, so the browser is',
            'showing a previous build. Styling changes on this branch will not',
            'appear until you rebuild:',
            '',
            '    npm run build',
            '',
            "Newest source file: {$newestFile}",
        ]));
    }

    /**
     * The most recently modified compiled-asset source, with its path so a
     * failure says which change is missing rather than only that one is.
     *
     * @return array{0: int, 1: string}
     */
    private function newestSourceFile(): array
    {
        $newest = 0;
        $newestFile = '(none)';

        foreach (self::COMPILED_FROM as $directory) {
            $path = resource_path($directory);

            if (! is_dir($path)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            ) as $file) {
                if ($file->isFile() && $file->getMTime() > $newest) {
                    $newest = $file->getMTime();
                    $newestFile = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        return [$newest, $newestFile];
    }
}
