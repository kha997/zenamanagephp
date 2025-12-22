<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SupportTicketController extends Controller
{
    /**
     * Display a listing of support tickets
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

            $tickets = SupportTicket::where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->with(['user', 'assignedTo'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tickets
            ]);

        } catch (\Exception $e) {
            Log::error('Support tickets index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch support tickets']
            ], 500);
        }
    }

    /**
     * Store a newly created support ticket
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
                'subject' => 'required|string|max:255',
                'description' => 'required|string',
                'category' => 'nullable|string|in:technical,billing,feature_request,bug_report,general',
                'priority' => 'nullable|string|in:low,medium,high,urgent'
            ]);

            $ticket = SupportTicket::create([
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'category' => $validated['category'] ?? 'general',
                'priority' => $validated['priority'] ?? 'medium',
                'status' => 'open',
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id
            ]);

            return response()->json([
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Support ticket creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to create support ticket']
            ], 500);
        }
    }

    /**
     * Display the specified support ticket
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

            $ticket = SupportTicket::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->with(['user', 'assignedTo', 'messages.user'])
                ->first();

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Support ticket not found']
                ], 404);
            }

            return response()->json([
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
                'messages' => $ticket->messages
            ]);

        } catch (\Exception $e) {
            Log::error('Support ticket show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch support ticket']
            ], 500);
        }
    }

    /**
     * Update the specified support ticket
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

            $ticket = SupportTicket::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            // Check if user owns the ticket or is admin
            if (!$ticket || ($ticket->user_id !== (string)$user->id && $user->role !== 'admin')) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Support ticket not found']
                ], 404);
            }

            $validated = $request->validate([
                'subject' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string',
                'category' => 'nullable|string|in:technical,billing,feature_request,bug_report,general',
                'priority' => 'nullable|string|in:low,medium,high,urgent',
                'status' => 'nullable|string|in:open,in_progress,pending_customer,resolved,closed',
                'assigned_to' => 'nullable'
            ]);

            // Convert assigned_to to string if it's a Ulid object
            if (isset($validated['assigned_to'])) {
                $validated['assigned_to'] = (string) $validated['assigned_to'];
            }

            $ticket->update($validated);

            return response()->json([
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Support ticket update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update support ticket']
            ], 500);
        }
    }

    /**
     * Remove the specified support ticket
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

            $ticket = SupportTicket::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Support ticket not found']
                ], 404);
            }

            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Support ticket deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Support ticket delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to delete support ticket']
            ], 500);
        }
    }

    /**
     * Add message to support ticket
     */
    public function addMessage(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $ticket = SupportTicket::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Support ticket not found']
                ], 404);
            }

            $validated = $request->validate([
                'message' => 'required|string',
                'is_internal' => 'boolean'
            ]);

            $message = SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_internal' => $validated['is_internal'] ?? false
            ]);

            return response()->json([
                'id' => $message->id,
                'message' => $message->message,
                'is_internal' => $message->is_internal,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Support message creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to add message to support ticket']
            ], 500);
        }
    }
}