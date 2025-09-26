<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function audit()
    {
        return response()->json(['message' => 'Security Audit API - Coming Soon']);
    }
    
    public function logs()
    {
        return response()->json(['message' => 'Security Logs API - Coming Soon']);
    }
}
