<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    protected $proxies = null;

    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function __construct()
    {
        $configured = trim((string) env('TRUSTED_PROXIES', ''));
        if ($configured === '*') {
            $this->proxies = '*';

            return;
        }

        $proxies = array_values(array_filter(array_map(
            static fn (string $proxy): string => trim($proxy),
            explode(',', $configured)
        ), static fn (string $proxy): bool => $proxy !== ''));

        $this->proxies = ! empty($proxies) ? $proxies : null;
    }
}
