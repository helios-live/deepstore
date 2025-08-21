<?php

namespace HeliosLive\Deepstore\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class StorageCollector
{
    /**
     * @param string $src
     * @param string $dest
     * @return void
     */
    public function collect(string $src, string $dest): void
    {
        $includesDir = (array) config('deepstore.include_directories');
        $excludesDir = (array) config('deepstore.exclude_directories');
        $includes = (array) config('deepstore.include_files');
        $excludes = (array) config('deepstore.exclude_files');

        $finder = new Finder();
        $finder->files()->in($src);

        foreach ($includesDir as $dir) {
            $dir = (string) trim((string) $dir);
            if ($dir !== '') {
                $finder->path($dir);
            }
        }

        foreach ($excludesDir as $dir) {
            $dir = (string) trim((string) $dir);
            if ($dir !== '') {
                $finder->notPath($dir);
            }
        }

        foreach ($includes as $pattern) {
            $pattern = (string) trim((string) $pattern);
            if ($pattern !== '') {
                $finder->name($pattern);
            }
        }

        foreach ($excludes as $pattern) {
            $pattern = (string) trim((string) $pattern);
            if ($pattern !== '') {
                $finder->notName($pattern);
            }
        }

        foreach ($finder as $file) {
            $target = $dest . '/' . $file->getRelativePathname();
            File::ensureDirectoryExists((string) dirname($target));
            File::copy($file->getRealPath(), $target);
        }
    }
}
