<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class WebSocketAuthController extends Controller
{
    public function authenticate(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'token' => bin2hex(random_bytes(16)),
            'socket_id' => uniqid('socket_', true),
            'channel' => 'dashboard',
        ]);
    }
}
