<?php

namespace HeliosLive\Deepstore\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class WebhookNotifier
{
    /**
     * @param bool $success
     * @param string $command
     * @return void
     */
    public function notify(bool $success, string $command): void
    {
        $url = (string) config('deepstore.forge_webhook_url');
        if ($url === '') {
            return;
        }

        $payload = [
            'status' => $success ? 'success' : 'failed',
            'command' => $command,
            'time' => Carbon::now()->toDateTimeString(),
        ];

        try {
            Http::timeout(5)->post($url, $payload);
        } catch (\Throwable) {
        }
    }
}
