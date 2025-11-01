<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobileController extends Controller
{
    /**
     * Mobile API endpoints for mobile app optimization
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Mobile API is working',
            'data' => [
                'version' => '1.0.0',
                'features' => [
                    'push_notifications' => true,
                    'offline_sync' => true,
                    'biometric_auth' => true
                ]
            ]
        ]);
    }

    public function optimize(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Mobile optimization applied',
            'data' => [
                'compression' => 'enabled',
                'caching' => 'optimized',
                'images' => 'webp_format'
            ]
        ]);
    }
}
