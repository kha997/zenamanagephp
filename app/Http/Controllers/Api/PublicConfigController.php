<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Public Config Controller
 * 
 * Returns public configuration values that don't require authentication
 * Used for feature flags that affect public pages (e.g., signup)
 */
class PublicConfigController extends Controller
{
    /**
     * Get public feature flags
     * 
     * GET /api/public/config
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'public_signup_enabled' => config('features.auth.public_signup_enabled', false),
        ]);
    }
}

