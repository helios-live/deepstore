<?php

namespace HeliosLive\Deepstore\Services;

use Illuminate\Support\Facades\Process;

class TarGzArchiver
{
    /**
     * @param string $sourceDir
     * @param string $tarGzPath
     * @return bool
     */
    public function create(string $sourceDir, string $tarGzPath): bool
    {
        return Process::run(['tar', '-czf', $tarGzPath, '-C', $sourceDir, '.'])->successful();
    }
}
