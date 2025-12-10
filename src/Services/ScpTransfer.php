<?php

namespace HeliosLive\Deepstore\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ScpTransfer
{
    /**
     * @return bool
     */
    public function enabled(): bool
    {
        return filled(config('deepstore.remote_host'))
            && filled(config('deepstore.remote_user'))
            && filled(config('deepstore.remote_path'));
    }

    /**
     * @param string $localPath
     * @param string $archiveName
     * @return bool
     */
    public function send(string $localPath, string $archiveName): bool
    {
        $remoteHost = (string) config('deepstore.remote_host');
        $remoteUser = (string) config('deepstore.remote_user');
        $remotePath = rtrim((string) config('deepstore.remote_path'), '/');
        $remotePort = (string) config('deepstore.remote_port');
        $sshKey = (string) config('deepstore.ssh_key_path');

        $destination = "{$remoteUser}@{$remoteHost}:{$remotePath}/{$archiveName}";

        $cmd = [
            'scp',
            '-P', $remotePort,
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
        ];

        if ($sshKey !== '' && File::exists($sshKey)) {
            $cmd[] = '-i';
            $cmd[] = $sshKey;
        }

        $cmd[] = $localPath;
        $cmd[] = $destination;

        $proccess = Process::run($cmd);

        if ($proccess->failed()) {
            Log:error('SCP transfer failed: ' . $proccess->errorOutput());
        }

        return $proccess->successful();
    }
    public function enforceRemoteRetention(
        string $remoteUser,
        string $remoteHost,
        string $remotePort,
        string $sshKey,
        string $remotePath
    ): void {
        $latestToKeep = (int) config('deepstore.retention.latest', 7);
        $keepFirstOfMonth = (bool) config('deepstore.retention.keep_first_of_month', true);
        $prefix = (string) config('deepstore.archive_prefix', 'archive_');
        $dateFormat = (string) config('deepstore.date_format', 'Y-m-d');

        // 1. Fetch remote filenames via SSH
        $lsCmd = 'ls -1 ' . escapeshellarg($remotePath) . ' 2>/dev/null';

        $ssh = [
            'ssh',
            '-p', $remotePort,
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
        ];

        if ($sshKey !== '' && File::exists($sshKey)) {
            $ssh[] = '-i';
            $ssh[] = $sshKey;
        }

        $ssh[] = $remoteUser . '@' . $remoteHost;
        $ssh[] = $lsCmd;

        $result = Process::run($ssh);


        if ($result->successful() === false) {
            return;
        }

        $files = array_filter(
            explode("\n", trim($result->output())),
            static fn ($v): bool => $v !== ''
        );

        // 2. Use BackupRetention to figure out what to delete
        $retention = new BackupRetention();
        $toDelete = $retention->enforceOnList(
            $files,
            $latestToKeep,
            $keepFirstOfMonth,
            $prefix,
            $dateFormat
        );

        if ($toDelete === []) {
            return;
        }
        $deleteCmd = 'rm' ;

        $del = [
            'ssh',
            '-p', $remotePort,
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
        ];

        if ($sshKey !== '' && File::exists($sshKey)) {
            $del[] = '-i';
            $del[] = $sshKey;
        }

        $del[] = $remoteUser . '@' . $remoteHost;
        $del[] = $deleteCmd;

        // 3. Delete files remotely
        $filesToDelete = [];

        foreach ($toDelete as $file) {
            $filesToDelete[] = escapeshellarg($remotePath . '/' . $file);
        }

        $del = array_merge($del, $filesToDelete);

        Process::run($del);

    }

}
