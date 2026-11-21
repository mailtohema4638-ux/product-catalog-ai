<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Gives every request a correlation id, shown in error responses and attached to every log line. */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        // Always generated server-side: an inbound id is client-controlled and would be log-injectable.
        $id = (string) Str::uuid();

        $request->attributes->set('request_id', $id);
        Log::withContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
