<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyVoiceGatewayRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('voice_gateway.internal_api.shared_secret', '');
        $provided = (string) ($request->header('X-Voice-Gateway-Secret') ?? '');

        abort_unless(
            $expected !== '' && hash_equals($expected, $provided),
            Response::HTTP_UNAUTHORIZED,
            'Unauthorized voice gateway request.',
        );

        return $next($request);
    }
}
