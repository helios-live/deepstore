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
    public function enforce(string $backupDir, int $latestToKeep, bool $keepFirstOfMonth, string $prefix, string $dateFormat): void
    {
        if (!File::isDirectory($backupDir)) {
            return;
        }

        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{4}-\d{2}-\d{2})\.tar\.gz$/';

        $files = [];
        foreach (File::files($backupDir) as $file) {
            $name = $file->getFilename();
            if (preg_match($pattern, $name, $m) === 1) {
                try {
                    $date = Carbon::createFromFormat($dateFormat, $m[1])->startOfDay();
                    $files[] = [
                        'path' => $file->getPathname(),
                        'date' => $date,
                        'ym' => $date->format('Y-m'),
                    ];
                } catch (\Throwable) {
                }
            }
        }

        if ($files === []) {
            return;
        }

        usort($files, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        $keep = [];

        foreach (array_slice($files, 0, max(0, $latestToKeep)) as $f) {
            $keep[$f['path']] = true;
        }

        if ($keepFirstOfMonth) {
            $firstByMonth = [];
            foreach (array_reverse($files) as $f) {
                if (!isset($firstByMonth[$f['ym']])) {
                    $firstByMonth[$f['ym']] = $f;
                }
            }
            foreach ($firstByMonth as $f) {
                $keep[$f['path']] = true;
            }
        }

        foreach ($files as $f) {
            if (!isset($keep[$f['path']]) && File::exists($f['path'])) {
                File::delete($f['path']);
            }
        }
    }
}
