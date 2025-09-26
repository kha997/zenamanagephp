<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SidebarController extends Controller
{
    public function getConfig()
    {
        return response()->json(['message' => 'App Sidebar Config API - Coming Soon']);
    }
    
    public function getBadges()
    {
        return response()->json(['message' => 'App Sidebar Badges API - Coming Soon']);
    }
    
    public function getDefault($role)
    {
        return response()->json(['message' => "App Sidebar Default for {$role} - Coming Soon"]);
    }
}
