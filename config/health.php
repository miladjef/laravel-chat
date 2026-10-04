<?php

return [
    'check_reverb' => (bool) env('HEALTH_CHECK_REVERB', false),
    'reverb_host' => env('HEALTH_REVERB_HOST', '127.0.0.1'),
    'reverb_port' => (int) env('HEALTH_REVERB_PORT', env('REVERB_SERVER_PORT', 8080)),
    'socket_timeout_seconds' => (float) env('HEALTH_SOCKET_TIMEOUT', 0.5),
];
