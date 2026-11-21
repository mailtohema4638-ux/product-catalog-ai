<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets browsers and a future CDN cache successful catalogue reads briefly. */
class PublicCache
{
    public function handle(Request $request, Closure $next, int $seconds = 60): Response
    {
        $response = $next($request);

        if ($request->isMethodCacheable() && $response->isSuccessful()) {
            $response->setPublic();
            $response->setMaxAge($seconds);
        }

        return $response;
    }
}
