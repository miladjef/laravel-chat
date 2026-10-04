<?php

return [
    'history_limit' => (int) env('CHAT_HISTORY_LIMIT', 50),
    'history_ttl_minutes' => (int) env('CHAT_HISTORY_TTL_MINUTES', 30),
    'dom_message_limit' => (int) env('CHAT_DOM_MESSAGE_LIMIT', 300),
    'heartbeat_seconds' => (int) env('CHAT_HEARTBEAT_SECONDS', 180),
    'guest_stale_hours' => (int) env('CHAT_GUEST_STALE_HOURS', 24),
];
