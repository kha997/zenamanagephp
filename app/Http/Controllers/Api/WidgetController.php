<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WidgetController extends Controller
{
    /**
     * Display a listing of widgets
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $widgets = Widget::where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->with(['dashboard', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $widgets
            ]);

        } catch (\Exception $e) {
            Log::error('Widgets index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch widgets']
            ], 500);
        }
    }

    /**
     * Store a newly created widget
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'type' => 'required|string|in:kpi,chart,table,text',
                'title' => 'nullable|string|max:255',
                'config' => 'nullable|array',
                'position' => 'nullable|array',
                'dashboard_id' => 'nullable|integer|exists:dashboards,id'
            ]);

            // Generate name from title if not provided
            $name = $validated['name'] ?? $validated['title'] ?? 'Widget ' . time();

            // Ensure dashboard exists
            $dashboardId = $validated['dashboard_id'] ?? 1;
            if ($dashboardId === 1) {
                // Create default dashboard if it doesn't exist
                $dashboard = \App\Models\Dashboard::firstOrCreate(
                    ['id' => 1],
                    [
                        'name' => 'Default Dashboard',
                        'description' => 'Default dashboard for widgets',
                        'user_id' => $user->id,
                        'tenant_id' => $user->tenant_id,
                        'is_default' => true
                    ]
                );
                $dashboardId = $dashboard->id;
            }

            $widget = Widget::create([
                'name' => $name,
                'type' => $validated['type'],
                'settings' => [
                    'title' => $validated['title'] ?? $name,
                    'config' => $validated['config'] ?? [],
                    'position' => $validated['position'] ?? ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 3]
                ],
                'dashboard_id' => $dashboardId,
                'user_id' => (string)$user->id,
                'tenant_id' => (string)$user->tenant_id
            ]);

            Log::info('Widget created', [
                'widget_id' => $widget->id,
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'dashboard_id' => $dashboardId
            ]);

            return response()->json([
                'id' => $widget->id,
                'name' => $widget->name,
                'type' => $widget->type,
                'title' => $widget->settings['title'] ?? $widget->name,
                'config' => $widget->settings['config'] ?? [],
                'position' => $widget->settings['position'] ?? [],
                'created_at' => $widget->created_at,
                'updated_at' => $widget->updated_at
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Widget creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to create widget']
            ], 500);
        }
    }

    /**
     * Display the specified widget
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $widget = Widget::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->with(['dashboard', 'user'])
                ->first();

            if (!$widget) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Widget not found']
                ], 404);
            }

            return response()->json([
                'id' => $widget->id,
                'name' => $widget->name,
                'type' => $widget->type,
                'title' => $widget->settings['title'] ?? $widget->name,
                'config' => $widget->settings['config'] ?? [],
                'position' => $widget->settings['position'] ?? [],
                'created_at' => $widget->created_at,
                'updated_at' => $widget->updated_at
            ]);

        } catch (\Exception $e) {
            Log::error('Widget show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch widget']
            ], 500);
        }
    }

    /**
     * Update the specified widget
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $widget = Widget::where('id', $id)
                ->where('tenant_id', (string)$user->tenant_id)
                ->where('user_id', (string)$user->id)
                ->first();

            // Debug: Check what's actually in database
            $allWidgets = Widget::where('id', $id)->get();
            Log::info('Widget debug', [
                'widget_id' => $id,
                'user_id' => (string)$user->id,
                'tenant_id' => (string)$user->tenant_id,
                'all_widgets_with_id' => $allWidgets->toArray(),
                'widget_found' => $widget ? true : false
            ]);

            if (!$widget) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Widget not found']
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|string|in:kpi,chart,table,text',
                'title' => 'nullable|string|max:255',
                'config' => 'nullable|array',
                'position' => 'nullable|array'
            ]);

            $settings = $widget->settings;
            if (isset($validated['title'])) {
                $settings['title'] = $validated['title'];
            }
            if (isset($validated['config'])) {
                $settings['config'] = $validated['config'];
            }
            if (isset($validated['position'])) {
                $settings['position'] = $validated['position'];
            }

            $widget->update([
                'name' => $validated['name'] ?? $widget->name,
                'type' => $validated['type'] ?? $widget->type,
                'settings' => $settings
            ]);

            return response()->json([
                'id' => $widget->id,
                'name' => $widget->name,
                'type' => $widget->type,
                'title' => $widget->settings['title'] ?? $widget->name,
                'config' => $widget->settings['config'] ?? [],
                'position' => $widget->settings['position'] ?? [],
                'created_at' => $widget->created_at,
                'updated_at' => $widget->updated_at
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Widget update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update widget']
            ], 500);
        }
    }

    /**
     * Remove the specified widget
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $widget = Widget::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$widget) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Widget not found']
                ], 404);
            }

            $widget->delete();

            return response()->json([
                'success' => true,
                'message' => 'Widget deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Widget delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to delete widget']
            ], 500);
        }
    }
}