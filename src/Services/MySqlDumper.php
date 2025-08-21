<?php

namespace HeliosLive\Deepstore;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * @psalm-suppress MixedArrayAccess
 * @psalm-suppress MixedAssignment
 */
class MySqlDumper
{
    /**
     * @param string $backupDir
     * @return string|false
     */
    public function dump(string $backupDir): string|false
    {
        $connection = (string) Config::get('database.default');
        $db = (array) Config::get("database.connections.{$connection}");

        if (($db['driver'] ?? null) !== 'mysql') {
            return false;
        }

        $include = (array) config('deepstore.include_tables');
        $exclude = (array) config('deepstore.exclude_tables');

        $file = "{$backupDir}/{$db['database']}.sql";

        $cmd = [
            'mysqldump',
            "--host={$db['host']}",
            "--port={$db['port']}",
            "--user={$db['username']}",
            '--routines',
            '--result-file=' . $file,
        ];

        if (!empty($db['password'])) {
            $cmd[] = "--password={$db['password']}";
        }

        $cmd[] = (string) $db['database'];

        if (!empty($include)) {
            foreach ($include as $table) {
                $table = (string) $table;
                if ($table !== '') {
                    $cmd[] = $table;
                }
            }
        } else {
            foreach ($exclude as $table) {
                $table = (string) $table;
                if ($table !== '') {
                    $cmd[] = "--ignore-table={$db['database']}.$table";
                }
            }
        }

        return Process::run($cmd)->successful() && File::exists($file) ? $file : false;
    }
}
