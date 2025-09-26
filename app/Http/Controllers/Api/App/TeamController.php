<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'App Team API - Coming Soon']);
    }
    
    public function store(Request $request)
    {
        return response()->json(['message' => 'Create App Team Member - Coming Soon']);
    }
    
    public function show($id)
    {
        return response()->json(['message' => 'Show App Team Member - Coming Soon']);
    }
    
    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Update App Team Member - Coming Soon']);
    }
    
    public function destroy($id)
    {
        return response()->json(['message' => 'Delete App Team Member - Coming Soon']);
    }
    
    public function invite(Request $request)
    {
        return response()->json(['message' => 'Invite Team Member - Coming Soon']);
    }
}
