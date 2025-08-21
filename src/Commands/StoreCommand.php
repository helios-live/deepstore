<?php

namespace HeliosLive\Deepstore\Commands;

use Carbon\Carbon;
use HeliosLive\Deepstore\Services\MySqlDumper;
use HeliosLive\Deepstore\Services\ScpTransfer;
use HeliosLive\Deepstore\Services\TarGzArchiver;
use HeliosLive\Deepstore\Services\BackupRetention;
use HeliosLive\Deepstore\Services\WebhookNotifier;
use HeliosLive\Deepstore\Services\StorageCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * @psalm-suppress MixedAssignment
 */
class StoreCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'deepstore:store';

    /**
     * @var string
     */
    protected $description = 'Create a tar.gz backup of database and storage, send to remote, notify Forge';

    /**
     * @return int
     */
    public function handle(): int
    {
        $ok = false;
        $archiveCreated = false;

        $backupBaseDir = rtrim((string) config('deepstore.backup_path'), '/');
        $dateFormat = (string) config('deepstore.date_format', 'Y-m-d');
        $archivePrefix = (string) config('deepstore.archive_prefix', 'archive_');

        try {
            File::ensureDirectoryExists($backupBaseDir);

            $timestamp = Carbon::now()->format($dateFormat);
            $archiveName = $archivePrefix . $timestamp . '.tar.gz';
            $archivePath = $backupBaseDir . '/' . $archiveName;

            $tempDir = $backupBaseDir . '/temp_' . uniqid();
            $tempSqlDir = $tempDir . '/sql';
            $tempStoreDir = $tempDir . '/storage';
            File::ensureDirectoryExists($tempSqlDir);
            File::ensureDirectoryExists($tempStoreDir);

            $sqlPath = (new MySqlDumper())->dump($tempSqlDir);
            if ($sqlPath === false) {
                throw new \RuntimeException('Database dump failed.');
            }

            (new StorageCollector())->collect(storage_path(), $tempStoreDir);

            $created = (new TarGzArchiver())->create($tempDir, $archivePath);
            if ($created === false) {
                throw new \RuntimeException('Could not create tar.gz archive.');
            }

            $archiveCreated = true;

            $transfer = new ScpTransfer();
            if ($transfer->enabled()) {
                $isSent = $transfer->send($archivePath, $archiveName);
                if ($isSent === false) {
                    throw new \RuntimeException('Remote transfer failed.');
                }
            }

            $ok = true;
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        } finally {
            if (isset($tempDir) && File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }

            if ($archiveCreated) {
                (new BackupRetention())->enforce(
                    $backupBaseDir,
                    (int) config('deepstore.retention.latest', 7),
                    (bool) config('deepstore.retention.keep_first_of_month', true),
                    (string) config('deepstore.archive_prefix', 'archive_'),
                    (string) config('deepstore.date_format', 'Y-m-d')
                );
            }

            (new WebhookNotifier())->notify($ok, 'deepstore:store');
        }
    }
}
