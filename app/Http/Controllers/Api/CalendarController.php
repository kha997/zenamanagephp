<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CalendarController extends Controller
{
    /**
     * Display a listing of calendar events
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

            $events = CalendarEvent::where('tenant_id', $user->tenant_id)
                ->when($request->has('start_date'), function ($query) use ($request) {
                    $query->where('start_date', '>=', $request->start_date);
                })
                ->when($request->has('end_date'), function ($query) use ($request) {
                    $query->where('end_date', '<=', $request->end_date);
                })
                ->orderBy('start_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $events
            ]);
        } catch (\Exception $e) {
            Log::error('Calendar index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch calendar events']
            ], 500);
        }
    }

    /**
     * Store a newly created calendar event
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
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'location' => 'nullable|string|max:255',
                'event_type' => 'nullable|string|max:100',
                'is_all_day' => 'boolean',
                'reminder_minutes' => 'nullable|integer|min:0'
            ]);

            $event = CalendarEvent::create([
                'tenant_id' => $user->tenant_id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'location' => $validated['location'] ?? null,
                'event_type' => $validated['event_type'] ?? 'general',
                'is_all_day' => $validated['is_all_day'] ?? false,
                'reminder_minutes' => $validated['reminder_minutes'] ?? null,
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $event
            ], 201);
        } catch (\Exception $e) {
            Log::error('Calendar store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to create calendar event']
            ], 500);
        }
    }

    /**
     * Display the specified calendar event
     */
    public function show(CalendarEvent $event): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            if ($event->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied: Event belongs to different tenant']
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $event
            ]);
        } catch (\Exception $e) {
            Log::error('Calendar show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch calendar event']
            ], 500);
        }
    }

    /**
     * Update the specified calendar event
     */
    public function update(Request $request, CalendarEvent $event): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            if ($event->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied: Event belongs to different tenant']
                ], 403);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date',
                'location' => 'nullable|string|max:255',
                'event_type' => 'nullable|string|max:100',
                'is_all_day' => 'boolean',
                'reminder_minutes' => 'nullable|integer|min:0'
            ]);

            $event->update($validated);
            $event->updated_by = $user->id;
            $event->save();

            return response()->json([
                'success' => true,
                'data' => $event
            ]);
        } catch (\Exception $e) {
            Log::error('Calendar update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update calendar event']
            ], 500);
        }
    }

    /**
     * Remove the specified calendar event
     */
    public function destroy(CalendarEvent $event): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            if ($event->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied: Event belongs to different tenant']
                ], 403);
            }

            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Calendar event deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Calendar destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to delete calendar event']
            ], 500);
        }
    }

    /**
     * Get calendar statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $stats = [
                'total_events' => CalendarEvent::where('tenant_id', $user->tenant_id)->count(),
                'upcoming_events' => CalendarEvent::where('tenant_id', $user->tenant_id)
                    ->where('start_date', '>=', now())->count(),
                'events_this_month' => CalendarEvent::where('tenant_id', $user->tenant_id)
                    ->whereMonth('start_date', now()->month)
                    ->whereYear('start_date', now()->year)->count(),
                'all_day_events' => CalendarEvent::where('tenant_id', $user->tenant_id)
                    ->where('is_all_day', true)->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Calendar stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch calendar statistics']
            ], 500);
        }
    }
}
