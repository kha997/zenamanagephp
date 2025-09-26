<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'App Templates API - Coming Soon']);
    }
    
    public function store(Request $request)
    {
        return response()->json(['message' => 'Create App Template - Coming Soon']);
    }
    
    public function show($id)
    {
        return response()->json(['message' => 'Show App Template - Coming Soon']);
    }
    
    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Update App Template - Coming Soon']);
    }
    
    public function destroy($id)
    {
        return response()->json(['message' => 'Delete App Template - Coming Soon']);
    }
}
