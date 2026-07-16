<?php

use Illuminate\Http\Request;

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set to '*' for cloud environments where the proxy IP is unknown, or a
    | comma-separated list of Nginx/load-balancer IPs in production.
    |
    */
    'proxies' => env('TRUSTED_PROXIES', '*'),

    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_AWS_ELB,
];
