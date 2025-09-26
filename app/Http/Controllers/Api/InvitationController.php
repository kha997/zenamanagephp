<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function accept($token)
    {
        return response()->json(['message' => 'Accept Invitation API - Coming Soon']);
    }
    
    public function processAcceptance($token)
    {
        return response()->json(['message' => 'Process Invitation Acceptance API - Coming Soon']);
    }
}
