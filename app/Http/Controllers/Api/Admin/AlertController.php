<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'Admin Alerts API - Coming Soon']);
    }
    
    public function store(Request $request)
    {
        return response()->json(['message' => 'Create Admin Alert - Coming Soon']);
    }
    
    public function show($id)
    {
        return response()->json(['message' => 'Show Admin Alert - Coming Soon']);
    }
    
    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Update Admin Alert - Coming Soon']);
    }
    
    public function destroy($id)
    {
        return response()->json(['message' => 'Delete Admin Alert - Coming Soon']);
    }
}
