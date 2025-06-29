<?php

namespace HeliosLive\Deepstore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

class StoreCommand extends Command
{
    protected $signature = 'deepstore:store';
    protected $description = 'Create a tar.gz backup of database and storage, send to remote, notify Forge';

    public function handle(): int
    {
        $ok = false;

        try {
            $backupBaseDir = rtrim(config('deepstore.backup_path'), '/');
            File::ensureDirectoryExists($backupBaseDir);

            $timestamp   = Carbon::now()->format('Y-m-d');
            $archiveName = "archive_{$timestamp}.tar.gz";
            $archivePath = $backupBaseDir . '/' . $archiveName;

            $tempDir      = $backupBaseDir . '/temp_' . uniqid();
            $tempSqlDir   = $tempDir . '/sql';
            $tempStoreDir = $tempDir . '/storage';
            File::ensureDirectoryExists($tempSqlDir);
            File::ensureDirectoryExists($tempStoreDir);

            $sql = $this->backupDatabase($tempSqlDir);
            if (!$sql) {
                throw new \RuntimeException('Database dump failed.');
            }

            $this->backupDirectory(storage_path(), $tempStoreDir);

            if (!$this->createTarGzArchive($tempDir, $archivePath)) {
                throw new \RuntimeException('Could not create tar.gz archive.');
            }

            if ($this->shouldTransferToRemote() &&
                !$this->transferToRemote($archivePath, $archiveName)) {
                throw new \RuntimeException('Remote transfer failed.');
            }

            $ok = true;
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        } finally {
            $this->sendForgeNotification($ok);
            if (isset($tempDir) && File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    protected function shouldTransferToRemote(): bool
    {
        return filled(config('deepstore.remote_host')) &&
            filled(config('deepstore.remote_user')) &&
            filled(config('deepstore.remote_path'));
    }

    protected function transferToRemote(string $local, string $name): bool
    {
        $remoteHost = config('deepstore.remote_host');
        $remoteUser = config('deepstore.remote_user');
        $remotePath = rtrim(config('deepstore.remote_path'), '/');
        $remotePort = (string) config('deepstore.remote_port');
        $sshKey     = config('deepstore.ssh_key_path');

        $destination = "{$remoteUser}@{$remoteHost}:{$remotePath}/{$name}";

        $cmd = [
            'scp',
            '-P', $remotePort,
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
        ];

        if ($sshKey && File::exists($sshKey)) {
            $cmd[] = '-i';
            $cmd[] = $sshKey;
        }

        $cmd[] = $local;
        $cmd[] = $destination;

        return Process::run($cmd)->successful();
    }

    protected function backupDatabase(string $backupDir): string|false
    {
        $connection = Config::get('database.default');
        $db         = Config::get("database.connections.{$connection}");

        if ($db['driver'] !== 'mysql') {
            return false;
        }

        $include = config('deepstore.include_tables');
        $exclude = config('deepstore.exclude_tables');

        $file = "{$backupDir}/{$db['database']}.sql";

        $cmd = [
            'mysqldump',
            "--host={$db['host']}",
            "--port={$db['port']}",
            "--user={$db['username']}",
            '--routines',
            '--result-file=' . $file,
        ];

        if ($db['password']) {
            $cmd[] = "--password={$db['password']}";
        }

        $cmd[] = $db['database'];

        if ($include) {
            foreach ($include as $table) {
                $cmd[] = $table;
            }
        } else {
            foreach ($exclude as $table) {
                $cmd[] = "--ignore-table={$db['database']}.{$table}";
            }
        }

        return Process::run($cmd)->successful() && File::exists($file) ? $file : false;
    }

    protected function backupDirectory(string $src, string $dest): void
    {
        $includesDir = config('deepstore.include_directories');
        $excludesDir = config('deepstore.exclude_directories');
        $includes    = config('deepstore.include_files');
        $excludes    = config('deepstore.exclude_files');

        $finder = new Finder();
        $finder->files()->in($src);

        foreach ($includesDir as $dir) {
            $finder->path($dir);
        }
        foreach ($excludesDir as $dir) {
            $finder->notPath($dir);
        }
        foreach ($includes as $pattern) {
            $finder->name($pattern);
        }
        foreach ($excludes as $pattern) {
            $finder->notName($pattern);
        }

        foreach ($finder as $file) {
            $target = $dest . '/' . $file->getRelativePathname();
            File::ensureDirectoryExists(dirname($target));
            File::copy($file->getRealPath(), $target);
        }
    }

    protected function createTarGzArchive(string $sourceDir, string $tarGzPath): bool
    {
        return Process::run(['tar', '-czf', $tarGzPath, '-C', $sourceDir, '.'])->successful();
    }

    protected function sendForgeNotification(bool $success): void
    {
        $url = config('deepstore.forge_webhook_url');
        if (!$url) {
            return;
        }

        $payload = [
            'status'  => $success ? 'success' : 'failed',
            'command' => 'deepstore:store',
            'time'    => Carbon::now()->toDateTimeString(),
        ];

        try {
            Http::timeout(5)->post($url, $payload);
        } catch (\Throwable) {
            // ignore
        }
    }
}