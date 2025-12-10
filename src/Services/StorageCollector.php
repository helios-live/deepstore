<?php

namespace HeliosLive\Deepstore\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class StorageCollector
{
    private const DEEPSTORE_PATH_PATTERN = '/deepstore/i';
    private const DEEPSTORE_NAME_PATTERN = '*deepstore*';
    private const ENV_FILE_NAME = '.env';

    /**
     * @param string $src
     * @param string $dest
     * @return void
     */
    public function collect(string $src, string $dest): void
    {
        $includesDir = (array) config('deepstore.include_directories');
        $excludesDir = (array) config('deepstore.exclude_directories');
        $whiteListedFiles = (array) config('deepstore.whitelist_files');
        $blackListedFiles = (array) config('deepstore.blacklist_files');
        $includes = (array) config('deepstore.include_files');
        $finder = new Finder();
        $includesFinder = new Finder();

        $finder->files()->in($src);
        $includesFinder->files()->in($src);

        $finder->notPath(self::DEEPSTORE_PATH_PATTERN);
        $finder->notName(self::DEEPSTORE_NAME_PATTERN);

        $includesFinder->notPath(self::DEEPSTORE_PATH_PATTERN);
        $includesFinder->notName(self::DEEPSTORE_NAME_PATTERN);

        foreach ($includesDir as $dir) {
            $dir = (string) trim((string) $dir);
            if ($dir !== '') {
                $finder->path($dir);
                $includesFinder->path($dir);
            }
        }

        foreach ($excludesDir as $dir) {
            $dir = (string) trim((string) $dir);
            if ($dir !== '') {
                $finder->notPath($dir);
                $includesFinder->notPath($dir);
            }
        }

        $whitelistPatterns = [];

        foreach ($whiteListedFiles as $pattern) {
            $pattern = (string) trim($pattern);
            if ($pattern !== '') {
                $whitelistPatterns[] = $pattern;
            }
        }

        foreach ($blackListedFiles as $pattern) {
            $pattern = (string) trim((string) $pattern);
            if ($pattern !== '') {
                $finder->notName($this->normalizeExtensionPattern($pattern));
            }
        }

        foreach ($includes as $pattern) {
            $pattern = (string) trim((string) $pattern);
            if ($pattern !== '') {
                $includesFinder->name($pattern);
            }
        }

        $files = [];

        foreach ($finder as $file) {
            if ($this->matchesWhitelist($file->getFilename(), $whitelistPatterns)) {
                $files[$file->getRealPath()] = $file;
            }
        }

        foreach ($includesFinder as $file) {
            $files[$file->getRealPath()] = $file;
        }

        foreach ($files as $file) {
            $target = $dest . '/' . $file->getRelativePathname();
            File::ensureDirectoryExists((string) dirname($target));
            File::copy($file->getRealPath(), $target);
        }

        $envPath = base_path(self::ENV_FILE_NAME);
        if (File::isFile($envPath)) {
            $envTargetBaseDir = dirname($dest);
            File::ensureDirectoryExists($envTargetBaseDir);
            $envTarget = $envTargetBaseDir . DIRECTORY_SEPARATOR . self::ENV_FILE_NAME;
            File::copy($envPath, $envTarget);
        }
    }

    /**
     * Normalize extension-like patterns such as ".xml" to "*.xml" for Finder::name().
     *
     * @param string $pattern
     * @return string
     */
    private function normalizeExtensionPattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return $pattern;
        }

        if (
            $pattern[0] === '.'
            && strpos($pattern, '*') === false
            && strpos($pattern, '?') === false
        ) {
            return '*' . $pattern;
        }

        return $pattern;
    }

    /**
     * Whitelist semantics:
     *  - If list is empty => allow all.
     *  - If entry starts with "." (e.g. ".xml", ".exe") => match by extension.
     *  - If entry does NOT start with "." (e.g. "report") => match if substring is in the filename.
     *
     * @param string             $fileName
     * @param array<int, string> $whitelistPatterns
     * @return bool
     */
    private function matchesWhitelist(string $fileName, array $whitelistPatterns): bool
    {
        if ($whitelistPatterns === []) {
            return true;
        }

        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $lowerName = strtolower($fileName);

        foreach ($whitelistPatterns as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }

            if ($pattern[0] === '.') {
                $wantedExtension = substr($pattern, 1);
                if ($wantedExtension !== '' && $wantedExtension === $extension) {
                    return true;
                }
            } else {
                if (strpos($lowerName, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

}
