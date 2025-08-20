<?php

namespace HeliosLive\Deepstore;

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

        return Process::run($cmd)->successful();
    }
}
