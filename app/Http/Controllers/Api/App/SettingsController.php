<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'App Settings API - Coming Soon']);
    }
    
    public function update(Request $request)
    {
        return response()->json(['message' => 'Update App Settings - Coming Soon']);
    }
    
    public function general()
    {
        return response()->json(['message' => 'App General Settings - Coming Soon']);
    }
    
    public function updateGeneral(Request $request)
    {
        return response()->json(['message' => 'Update App General Settings - Coming Soon']);
    }
    
    public function security()
    {
        return response()->json(['message' => 'App Security Settings - Coming Soon']);
    }
    
    public function updateSecurity(Request $request)
    {
        return response()->json(['message' => 'Update App Security Settings - Coming Soon']);
    }
    
    public function notifications()
    {
        return response()->json(['message' => 'App Notification Settings - Coming Soon']);
    }
    
    public function updateNotifications(Request $request)
    {
        return response()->json(['message' => 'Update App Notification Settings - Coming Soon']);
    }
}
