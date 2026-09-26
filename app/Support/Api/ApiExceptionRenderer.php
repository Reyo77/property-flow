<?php

namespace App\Support\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Every API error has the same shape: `{"message": "...", "code": "..."}`, plus `errors` (field =>
 * messages) for validation failures. `code` is stable and meant for clients to branch on; the
 * message is for people. Internal details (model classes, stack traces) never leak.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => $this->respond(422, 'validation_failed', $exception->getMessage(), ['errors' => $exception->errors()]),
            $exception instanceof AuthenticationException => $this->respond(401, 'unauthenticated', __('Unauthenticated.')),
            $exception instanceof AuthorizationException, $exception instanceof AccessDeniedHttpException => $this->respond(403, 'forbidden', $this->forbiddenMessage($exception)),
            $exception instanceof ModelNotFoundException, $exception instanceof NotFoundHttpException => $this->respond(404, 'not_found', __('Not found.')),
            $exception instanceof ThrottleRequestsException => $this->respond(429, 'too_many_requests', __('Too many requests. Try again shortly.'), headers: $exception->getHeaders()),
            $exception instanceof MethodNotAllowedHttpException => $this->respond(405, 'method_not_allowed', __('Method not allowed.'), headers: $exception->getHeaders()),
            $exception instanceof HttpExceptionInterface => $this->respond($exception->getStatusCode(), 'http_error', $exception->getMessage() ?: __('Request failed.'), headers: $exception->getHeaders()),
            config('app.debug') === true => null,
            default => $this->respond(500, 'server_error', __('Server error.')),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    private function respond(int $status, string $code, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code, ...$extra], $status, $headers);
    }

    private function forbiddenMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        return $message === '' || $message === 'This action is unauthorized.' ? __('You are not allowed to do this.') : $message;
    }
}
