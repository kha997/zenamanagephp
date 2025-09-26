<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function logs()
    {
        return response()->json(['message' => 'Activity Logs API - Coming Soon']);
    }
    
    public function audit()
    {
        return response()->json(['message' => 'Activity Audit API - Coming Soon']);
    }
}
