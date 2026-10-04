<?php

return [
    /*
     * Comma separated proxy IPs / CIDRs. "REMOTE_ADDR" trusts only the
     * immediate reverse proxy. Avoid "*" unless direct access to PHP is blocked.
     */
    'proxies' => env('TRUSTED_PROXIES'),
];
