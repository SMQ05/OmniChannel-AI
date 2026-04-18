<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Symfony\Component\HttpFoundation\Request;

class TrustProxies extends Middleware
{
    public function __construct()
    {
        $trustedProxies = (string) env('TRUSTED_PROXIES', '*');

        $this->proxies = $trustedProxies === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $trustedProxies))));
    }

    /**
     * Trust all proxies when TRUSTED_PROXIES=* is configured.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * Use the standard forwarded headers sent by Traefik / reverse proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PREFIX |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
