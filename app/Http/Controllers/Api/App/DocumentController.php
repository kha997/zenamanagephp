<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'App Documents API - Coming Soon']);
    }
    
    public function store(Request $request)
    {
        return response()->json(['message' => 'Create App Document - Coming Soon']);
    }
    
    public function show($id)
    {
        return response()->json(['message' => 'Show App Document - Coming Soon']);
    }
    
    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Update App Document - Coming Soon']);
    }
    
    public function destroy($id)
    {
        return response()->json(['message' => 'Delete App Document - Coming Soon']);
    }
    
    public function approvals()
    {
        return response()->json(['message' => 'Document Approvals - Coming Soon']);
    }
}
