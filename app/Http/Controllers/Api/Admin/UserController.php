<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'Admin Users API - Coming Soon']);
    }
    
    public function store(Request $request)
    {
        return response()->json(['message' => 'Create Admin User - Coming Soon']);
    }
    
    public function show($id)
    {
        return response()->json(['message' => 'Show Admin User - Coming Soon']);
    }
    
    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Update Admin User - Coming Soon']);
    }
    
    public function destroy($id)
    {
        return response()->json(['message' => 'Delete Admin User - Coming Soon']);
    }
}
