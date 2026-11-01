<?php

return [

    /*
     * Proxies whose X-Forwarded-* headers are believed: "*" for whatever proxy connects (only
     * safe when the app is unreachable except through it), or a comma-separated list of IPs/CIDRs.
     * Unset trusts nothing, which is right for a server with no proxy in front of it.
     */
    'proxies' => env('TRUSTED_PROXIES'),

];
