<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *   title="ZenaManage API",
 *   version="1.0.0"
 * )
 */
class HealthController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/health",
     *   tags={"System"},
     *   summary="Health check endpoint",
     *   description="Returns the health status of the API",
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="status", type="string", example="ok"),
     *       @OA\Property(property="timestamp", type="string", format="date-time")
     *     )
     *   )
     * )
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }
}

