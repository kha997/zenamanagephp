<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Admin dashboard API endpoints
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Admin dashboard data',
            'data' => [
                'stats' => [
                    'total_users' => 0,
                    'total_projects' => 0,
                    'total_tenants' => 0,
                    'active_sessions' => 0
                ],
                'recent_activities' => [],
                'system_health' => 'good'
            ]
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'users_count' => 0,
                'projects_count' => 0,
                'tasks_count' => 0,
                'revenue' => 0
            ]
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'database' => 'connected',
                'cache' => 'working',
                'queue' => 'running',
                'storage' => 'available'
            ]
        ]);
    }
}
