<?php declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait DelegatesToApiControllers
{
    private function buildApiRequest(Request $request, array $payload = []): Request
    {
        $apiRequest = Request::create(
            $request->fullUrl(),
            $request->method(),
            $payload,
            $request->cookies->all(),
            [],
            $request->server->all()
        );

        $apiRequest->headers->replace($request->headers->all());

        foreach ($request->attributes->all() as $key => $value) {
            $apiRequest->attributes->set($key, $value);
        }

        $apiRequest->setLaravelSession($request->session());
        $apiRequest->setUserResolver(static fn () => $request->user());

        return $apiRequest;
    }

    private function handleMutationResponse(JsonResponse $response, string $successUrl, string $successMessage): RedirectResponse
    {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return redirect($successUrl)->with('success', $successMessage);
        }

        return $this->handleErrorResponse($response);
    }

    private function handleErrorResponse(JsonResponse $response): RedirectResponse
    {
        $payload = $response->getData(true);

        if ($response->getStatusCode() === 422 && isset($payload['data']) && is_array($payload['data'])) {
            return back()->withErrors($payload['data'])->withInput();
        }

        return back()
            ->withInput()
            ->with('error', (string) ($payload['message'] ?? 'Không thể xử lý yêu cầu.'));
    }
}
