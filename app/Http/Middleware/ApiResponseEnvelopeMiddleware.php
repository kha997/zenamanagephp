<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrap all successful API responses with the standard envelope format.
 */
class ApiResponseEnvelopeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$response instanceof JsonResponse) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $payload = $response->getData(true);

        if (!is_array($payload)) {
            return $response;
        }

        $hasSuccess = array_key_exists('success', $payload);
        $hasData = array_key_exists('data', $payload);

        if ($hasSuccess && $hasData) {
            if ($payload['success'] !== true) {
                $payload['success'] = true;
                $response->setData($payload);
            }
            return $response;
        }

        $dataPayload = $hasData ? $payload['data'] : $payload;

        if (!$hasData && is_array($dataPayload)) {
            unset($dataPayload['success'], $dataPayload['status'], $dataPayload['message']);
        }

        $envelope = [
            'success' => true,
            'data' => $dataPayload,
        ];

        if (isset($payload['message'])) {
            $envelope['message'] = $payload['message'];
        }

        $response->setData($envelope);

        return $response;
    }
}
