<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

class ThrottleRequestsByIp extends ThrottleRequests
{
    /**
     * Rate-limit by client IP so throttling works correctly behind trusted proxies.
     */
    protected function resolveRequestSignature($request): string
    {
        return sha1($request->ip() ?? 'unknown');
    }
}
