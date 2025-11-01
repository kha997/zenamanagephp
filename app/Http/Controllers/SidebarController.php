<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class SidebarController extends Controller
{
    // protected $sidebarService;

    // public function __construct(SidebarService $sidebarService)
    // {
    //     $this->sidebarService = $sidebarService;
    // }

    public function getSidebarConfig(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['items' => []]);
            }

            $userRole = $user->roles->first()->name ?? 'client';
            $sidebarConfig = $this->sidebarService->getUserSidebarConfig($user, $userRole);

            return response()->json([
                'success' => true,
                'config' => $sidebarConfig
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load sidebar configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDefaultSidebarConfig($role)
    {
        try {
            $defaultConfig = $this->sidebarService->getDefaultSidebarConfig($role);
            
            return response()->json([
                'success' => true,
                'config' => $defaultConfig
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load default sidebar configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}