<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReadinessCheck
{
    public function run(): array
    {
        $checks = [
            'database' => $this->database(),
            'cache' => $this->cache(),
        ];

        if ((bool) config('health.check_reverb', false)) {
            $checks['reverb'] = $this->reverb();
        }

        return [
            'ok' => ! in_array(false, $checks, true),
            'checks' => array_map(static fn (bool $ok): string => $ok ? 'ok' : 'failed', $checks),
        ];
    }

    private function database(): bool
    {
        try {
            DB::connection()->select('select 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cache(): bool
    {
        try {
            $key = 'health:cache:'.bin2hex(random_bytes(8));
            Cache::put($key, 'ok', 10);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);
            return $ok;
        } catch (Throwable) {
            return false;
        }
    }

    private function reverb(): bool
    {
        $host = (string) config('health.reverb_host', '127.0.0.1');
        $port = (int) config('health.reverb_port', 8080);
        $timeout = (float) config('health.socket_timeout_seconds', 0.5);
        $socket = @fsockopen($host, $port, $errorNumber, $errorMessage, $timeout);

        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);
        return true;
    }
}
