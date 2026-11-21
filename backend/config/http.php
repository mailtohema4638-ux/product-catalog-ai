<?php

return [
    // Proxies (the gateway) allowed to set X-Forwarded-* headers: comma-separated IPs/CIDRs, or "*".
    // Empty trusts none, so a directly exposed API cannot have its client IP spoofed.
    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
        fn (string $proxy): bool => $proxy !== '',
    )),
];
