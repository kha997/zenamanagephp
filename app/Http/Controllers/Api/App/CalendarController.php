<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CalendarController extends Controller
{
    /**
     * Get calendar events for the authenticated user's tenant
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            $tenantId = $user->tenant_id;
            
            // Get date range from request
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
            
            // Get events based on user permissions
            $events = $this->getCalendarEvents($tenantId, $user->id, $startDate, $endDate);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'events' => $events,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ],
                    'tenant_id' => $tenantId
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Calendar API Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch calendar events'
            ], 500);
        }
    }
    
    /**
     * Create a new calendar event
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            $tenantId = $user->tenant_id;
            
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'type' => 'required|in:meeting,task,project,deadline,personal',
                'project_id' => 'nullable|integer',
                'task_id' => 'nullable|integer',
                'is_all_day' => 'boolean'
            ]);
            
            // Check permissions for project/task if specified
            if ($validated['project_id']) {
                if (!$this->hasProjectAccess($user->id, $validated['project_id'], $tenantId)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No access to specified project'
                    ], 403);
                }
            }
            
            if ($validated['task_id']) {
                if (!$this->hasTaskAccess($user->id, $validated['task_id'], $tenantId)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No access to specified task'
                    ], 403);
                }
            }
            
            // Create event (mock implementation)
            $event = [
                'id' => rand(1000, 9999),
                'title' => $validated['title'],
                'description' => $validated['description'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? $validated['start_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'type' => $validated['type'],
                'project_id' => $validated['project_id'],
                'task_id' => $validated['task_id'],
                'is_all_day' => $validated['is_all_day'] ?? false,
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'created_at' => now()->toISOString()
            ];
            
            $this->logAudit('calendar_event_created', $user->id, $tenantId, $event);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Event created successfully',
                'data' => $event
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Calendar Create Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create event'
            ], 500);
        }
    }
    
    /**
     * Update a calendar event
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            $tenantId = $user->tenant_id;
            
            // Check if user has access to this event
            if (!$this->hasEventAccess($user->id, $id, $tenantId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No access to this event'
                ], 403);
            }
            
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'sometimes|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'type' => 'sometimes|in:meeting,task,project,deadline,personal',
                'is_all_day' => 'boolean'
            ]);
            
            // Mock update
            $event = [
                'id' => $id,
                'title' => $validated['title'] ?? 'Updated Event',
                'description' => $validated['description'] ?? '',
                'start_date' => $validated['start_date'] ?? now()->toDateString(),
                'end_date' => $validated['end_date'] ?? now()->toDateString(),
                'start_time' => $validated['start_time'] ?? '09:00',
                'end_time' => $validated['end_time'] ?? '10:00',
                'type' => $validated['type'] ?? 'meeting',
                'is_all_day' => $validated['is_all_day'] ?? false,
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'updated_at' => now()->toISOString()
            ];
            
            $this->logAudit('calendar_event_updated', $user->id, $tenantId, $event);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Event updated successfully',
                'data' => $event
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Calendar Update Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update event'
            ], 500);
        }
    }
    
    /**
     * Delete a calendar event
     */
    public function destroy($id)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            $tenantId = $user->tenant_id;
            
            // Check if user has access to this event
            if (!$this->hasEventAccess($user->id, $id, $tenantId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No access to this event'
                ], 403);
            }
            
            $this->logAudit('calendar_event_deleted', $user->id, $tenantId, ['event_id' => $id]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Event deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Calendar Delete Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete event'
            ], 500);
        }
    }
    
    /**
     * Get upcoming events
     */
    public function upcoming(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            $tenantId = $user->tenant_id;
            
            $limit = $request->input('limit', 10);
            $days = $request->input('days', 7);
            
            $startDate = now()->toDateString();
            $endDate = now()->addDays($days)->toDateString();
            
            $events = $this->getCalendarEvents($tenantId, $user->id, $startDate, $endDate);
            
            // Sort by date and limit
            usort($events, function($a, $b) {
                return strtotime($a['start_date'] . ' ' . $a['start_time']) - strtotime($b['start_date'] . ' ' . $b['start_time']);
            });
            
            $events = array_slice($events, 0, $limit);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'events' => $events,
                    'limit' => $limit,
                    'days' => $days
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Calendar Upcoming Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch upcoming events'
            ], 500);
        }
    }
    
    /**
     * Get calendar events with permissions
     */
    private function getCalendarEvents($tenantId, $userId, $startDate, $endDate)
    {
        // Mock data - in real implementation, this would query the database
        $events = [
            [
                'id' => 1,
                'title' => 'Project Alpha Review',
                'description' => 'Review project progress and next steps',
                'start_date' => '2024-01-15',
                'end_date' => '2024-01-15',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'type' => 'meeting',
                'project_id' => 1,
                'task_id' => null,
                'is_all_day' => false,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'permissions' => [
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_view' => true
                ]
            ],
            [
                'id' => 2,
                'title' => 'Task Deadline - UI Design',
                'description' => 'Complete UI design for mobile app',
                'start_date' => '2024-01-18',
                'end_date' => '2024-01-18',
                'start_time' => '17:00',
                'end_time' => '17:00',
                'type' => 'deadline',
                'project_id' => 1,
                'task_id' => 5,
                'is_all_day' => false,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'permissions' => [
                    'can_edit' => true,
                    'can_delete' => false,
                    'can_view' => true
                ]
            ],
            [
                'id' => 3,
                'title' => 'Team Standup',
                'description' => 'Daily team standup meeting',
                'start_date' => '2024-01-20',
                'end_date' => '2024-01-20',
                'start_time' => '09:00',
                'end_time' => '09:30',
                'type' => 'meeting',
                'project_id' => null,
                'task_id' => null,
                'is_all_day' => false,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'permissions' => [
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_view' => true
                ]
            ]
        ];
        
        // Filter by date range
        return array_filter($events, function($event) use ($startDate, $endDate) {
            return $event['start_date'] >= $startDate && $event['start_date'] <= $endDate;
        });
    }
    
    /**
     * Check if user has access to a project
     */
    private function hasProjectAccess($userId, $projectId, $tenantId)
    {
        // Mock implementation - in real app, check project permissions
        return true; // For demo purposes
    }
    
    /**
     * Check if user has access to a task
     */
    private function hasTaskAccess($userId, $taskId, $tenantId)
    {
        // Mock implementation - in real app, check task permissions
        return true; // For demo purposes
    }
    
    /**
     * Check if user has access to an event
     */
    private function hasEventAccess($userId, $eventId, $tenantId)
    {
        // Mock implementation - in real app, check event ownership/permissions
        return true; // For demo purposes
    }
    
    /**
     * Log audit trail
     */
    private function logAudit($action, $userId, $tenantId, $data = [])
    {
        Log::info('Calendar Audit', [
            'action' => $action,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ]);
    }
}
