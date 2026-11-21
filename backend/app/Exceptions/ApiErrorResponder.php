<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Turns every failure on /api/* into one envelope:
 * {"error": {"code", "message", "details"?, "request_id"}}
 * Messages are written for end users; internals (stack traces, upstream bodies) never appear.
 */
class ApiErrorResponder
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $code, $message, $details, $headers] = match (true) {
            $e instanceof ValidationException => [422, 'validation_failed', 'Please check the highlighted fields.', $e->errors(), []],
            $e instanceof ProductNotFoundException,
            $e instanceof CategoryNotFoundException,
            $e instanceof NotFoundHttpException => [404, 'not_found', 'We could not find what you were looking for.', null, []],
            $e instanceof ProviderRateLimitedException => [503, 'catalogue_busy', 'The catalogue is busy right now. Please try again shortly.', null, $this->retryAfter($e->retryAfterSeconds)],
            $e instanceof ProviderUnavailableException => [503, 'catalogue_unavailable', 'The catalogue is temporarily unavailable. Please try again shortly.', null, $this->retryAfter(30)],
            $e instanceof TooManyRequestsHttpException => [429, 'too_many_requests', 'Too many requests. Please wait a moment and try again.', null, $e->getHeaders()],
            $e instanceof MethodNotAllowedHttpException => [405, 'method_not_allowed', 'That method is not allowed here.', null, $e->getHeaders()],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http_error', 'The request could not be completed.', null, $e->getHeaders()],
            default => [500, 'server_error', 'Something went wrong on our side. Please try again.', null, []],
        };

        $requestId = $request->attributes->get('request_id') ?? (string) Str::uuid();

        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }
        $error['request_id'] = $requestId;

        return response()->json(['error' => $error], $status, $headers + ['X-Request-Id' => $requestId]);
    }

    /** @return array<string, int> */
    private function retryAfter(?int $seconds): array
    {
        return $seconds === null ? [] : ['Retry-After' => $seconds];
    }
}
