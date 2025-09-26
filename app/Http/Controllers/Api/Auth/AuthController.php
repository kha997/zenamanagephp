<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        return response()->json(['message' => 'Auth Login API - Coming Soon']);
    }
    
    public function logout()
    {
        return response()->json(['message' => 'Auth Logout API - Coming Soon']);
    }
    
    public function refresh()
    {
        return response()->json(['message' => 'Auth Refresh API - Coming Soon']);
    }
    
    public function me()
    {
        return response()->json(['message' => 'Auth Me API - Coming Soon']);
    }
}
