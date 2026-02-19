<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\Widget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'dashboard_id' => 'required|string|exists:dashboards,id',
            'type' => 'required|string',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'config' => 'sometimes|array',
            'position' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $dashboard = Dashboard::find($request->input('dashboard_id'));

        if (!$dashboard || $dashboard->user_id !== $user->id) {
            return response()->json(['message' => 'Dashboard not accessible'], 403);
        }

        $widget = Widget::create([
            'dashboard_id' => $dashboard->id,
            'tenant_id' => $dashboard->tenant_id,
            'user_id' => $user->id,
            'name' => $request->input('title') ?? $request->input('name') ?? 'widget',
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'config' => $request->input('config', []),
            'position' => $request->input('position', []),
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json($widget, 201);
    }

    public function update(Request $request, Widget $widget): JsonResponse
    {
        $user = $request->user();

        if (!$user || $widget->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'config' => 'sometimes|array',
            'position' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $widget->update([
            'name' => $request->input('title') ?? $widget->name,
            'description' => $request->input('description', $widget->description),
            'config' => $request->input('config', $widget->config),
            'position' => $request->input('position', $widget->position),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $widget->is_active,
        ]);

        return response()->json($widget);
    }

    public function destroy(Request $request, Widget $widget): JsonResponse
    {
        $user = $request->user();

        if (!$user || $widget->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $widget->delete();

        return response()->json(['success' => true]);
    }
}
