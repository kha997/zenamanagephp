<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SecretsRotationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SecretsController extends Controller
{
    protected $secretsRotationService;

    public function __construct(SecretsRotationService $secretsRotationService)
    {
        $this->secretsRotationService = $secretsRotationService;
    }

    /**
     * Rotate secrets
     */
    public function rotate(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all');
        
        switch ($type) {
            case 'jwt':
                $result = $this->secretsRotationService->rotateJwtSecret();
                break;
            case 'api_keys':
                $result = $this->secretsRotationService->rotateApiKeys();
                break;
            case 'database':
                $result = $this->secretsRotationService->rotateDatabaseCredentials();
                break;
            case 'all':
            default:
                $jwtResult = $this->secretsRotationService->rotateJwtSecret();
                $apiResult = $this->secretsRotationService->rotateApiKeys();
                $dbResult = $this->secretsRotationService->rotateDatabaseCredentials();
                
                $result = [
                    'status' => 'success',
                    'message' => 'All secrets rotated successfully',
                    'results' => [
                        'jwt' => $jwtResult,
                        'api_keys' => $apiResult,
                        'database' => $dbResult
                    ]
                ];
                break;
        }
        
        return response()->json($result);
    }

    /**
     * Get rotation status
     */
    public function status(): JsonResponse
    {
        $status = $this->secretsRotationService->getRotationStatus();
        
        return response()->json([
            'status' => 'success',
            'data' => $status
        ]);
    }

    /**
     * Schedule rotation
     */
    public function schedule(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $days = $request->input('days', 30);
        
        if (!$type) {
            return response()->json([
                'status' => 'error',
                'message' => 'Type is required'
            ], 400);
        }
        
        $result = $this->secretsRotationService->scheduleRotation($type, $days);
        
        return response()->json($result);
    }
}