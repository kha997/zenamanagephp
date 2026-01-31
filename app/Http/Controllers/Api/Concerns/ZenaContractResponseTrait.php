<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;

trait ZenaContractResponseTrait
{
    protected function zenaSuccessResponse($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            return $this->zenaPaginatedResponse($data, $message, $statusCode);
        }

        $payload = [
            'success' => true,
            'status' => 'success',
            'status_text' => 'success',
            'data' => $this->normalizeData($data),
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $statusCode);
    }

    private function zenaPaginatedResponse(LengthAwarePaginator $paginator, ?string $message, int $statusCode): JsonResponse
    {
        $payload = [
            'success' => true,
            'status' => 'success',
            'status_text' => 'success',
            'data' => $paginator->items(),
            'meta' => [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Ensure the envelope always carries an object (not null) so clients can rely on "data".
     */
    private function normalizeData($data)
    {
        if ($data === null) {
            return new stdClass();
        }

        return $data;
    }
}
