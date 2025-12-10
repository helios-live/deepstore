<?php

namespace HeliosLive\Deepstore\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class BackupRetention
{
    /**
     * @param string $backupDir
     * @param int $latestToKeep
     * @param bool $keepFirstOfMonth
     * @param string $prefix
     * @param string $dateFormat
     * @return void
     */
    public function enforce(
        string $backupDir,
        int $latestToKeep,
        bool $keepFirstOfMonth,
        string $prefix,
        string $dateFormat
    ): void {
        if (!File::isDirectory($backupDir)) {
            return;
        }

        $files = [];

        foreach (File::files($backupDir) as $file) {
            $name = $file->getFilename();

            $parsed = $this->parseArchiveName($name, $prefix, $dateFormat);
            if ($parsed === null) {
                continue;
            }

            $files[] = [
                'path' => $file->getPathname(),
                'name' => $name,
                'date' => $parsed['date'],
                'ym'   => $parsed['ym'],
            ];
        }

        if ($files === []) {
            return;
        }

        $toDelete = $this->resolveFilesToDelete($files, $latestToKeep, $keepFirstOfMonth);

        foreach ($toDelete as $file) {
            if (isset($file['path']) && File::exists($file['path'])) {
                File::delete($file['path']);
            }
        }
    }

    /**
     * Apply retention rules to a provided list of archive names
     * (used for remote retention via SSH).
     *
     * @param array<int, string> $fileNames
     * @param int $latestToKeep
     * @param bool $keepFirstOfMonth
     * @param string $prefix
     * @param string $dateFormat
     * @return array<int, string>  List of archive names that should be deleted
     */
    public function enforceOnList(
        array $fileNames,
        int $latestToKeep,
        bool $keepFirstOfMonth,
        string $prefix,
        string $dateFormat
    ): array {
        $files = [];

        foreach ($fileNames as $name) {
            $name = (string) $name;

            $parsed = $this->parseArchiveName($name, $prefix, $dateFormat);
            if ($parsed === null) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'date' => $parsed['date'],
                'ym'   => $parsed['ym'],
            ];
        }

        if ($files === []) {
            return [];
        }

        $toDelete = $this->resolveFilesToDelete($files, $latestToKeep, $keepFirstOfMonth);

        return array_values(
            array_map(
                static fn (array $file): string => $file['name'],
                $toDelete
            )
        );
    }

    /**
     * Parse a backup archive name and extract its date and year-month.
     *
     * Expected format: <prefix>YYYY-MM-DD.tar.gz
     *
     * @param string $name
     * @param string $prefix
     * @param string $dateFormat
     * @return array{date: \Carbon\Carbon, ym: string}|null
     */
    private function parseArchiveName(string $name, string $prefix, string $dateFormat): ?array
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{4}-\d{2}-\d{2})\.tar\.gz$/';

        if (preg_match($pattern, $name, $matches) !== 1) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat($dateFormat, $matches[1])->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return [
            'date' => $date,
            'ym'   => $date->format('Y-m'),
        ];
    }

    /**
     * Core retention logic shared by both local and remote enforcement.
     *
     * @param array<int, array{date: \Carbon\Carbon, ym: string}> $files
     * @param int $latestToKeep
     * @param bool $keepFirstOfMonth
     * @return array<int, array<string, mixed>>
     */
    private function resolveFilesToDelete(
        array $files,
        int $latestToKeep,
        bool $keepFirstOfMonth
    ): array {
        if ($files === []) {
            return [];
        }

        usort(
            $files,
            static fn (array $a, array $b): int => $b['date'] <=> $a['date']
        );

        $keep = [];

        foreach (array_slice($files, 0, max(0, $latestToKeep)) as $file) {
            $keep[spl_object_hash((object) $file) ?: $file['ym'] . $file['date']->getTimestamp()] = $file;
        }

        if ($keepFirstOfMonth) {
            $firstByMonth = [];

            foreach (array_reverse($files) as $file) {
                if (!isset($firstByMonth[$file['ym']])) {
                    $firstByMonth[$file['ym']] = $file;
                }
            }

            foreach ($firstByMonth as $file) {
                $keep[spl_object_hash((object) $file) ?: $file['ym'] . $file['date']->getTimestamp()] = $file;
            }
        }

        $keepSet = [];
        foreach ($keep as $file) {
            $keepSet[$file['ym'] . $file['date']->getTimestamp()] = true;
        }

        $delete = [];

        foreach ($files as $file) {
            $key = $file['ym'] . $file['date']->getTimestamp();
            if (!isset($keepSet[$key])) {
                $delete[] = $file;
            }
        }

        return $delete;
    }
}
