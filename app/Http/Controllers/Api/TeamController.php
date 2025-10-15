<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TeamController extends Controller
{
    /**
     * Display a listing of teams
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

            $teams = Team::where('tenant_id', $user->tenant_id)
                ->with(['teamLead', 'members'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $teams
            ]);
        } catch (\Exception $e) {
            Log::error('Team index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch teams']
            ], 500);
        }
    }

    /**
     * Store a newly created team
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
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'team_lead_id' => 'nullable|exists:users,id',
                'department' => 'nullable|string|max:255',
                'is_active' => 'boolean'
            ]);

            $team = Team::create([
                'tenant_id' => $user->tenant_id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'team_lead_id' => $validated['team_lead_id'] ?? null,
                'department' => $validated['department'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $team->load(['teamLead', 'members'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Team store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to create team']
            ], 500);
        }
    }

    /**
     * Display the specified team
     */
    public function show(Team $team): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            if ($team->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied: Team belongs to different tenant']
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $team->load(['teamLead', 'members'])
            ]);
        } catch (\Exception $e) {
            Log::error('Team show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch team']
            ], 500);
        }
    }

    /**
     * Update the specified team
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            if ($team->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied: Team belongs to different tenant']
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'team_lead_id' => 'nullable|exists:users,id',
                'department' => 'nullable|string|max:255',
                'is_active' => 'boolean'
            ]);

            $team->update($validated);
            $team->updated_by = $user->id;
            $team->save();

            return response()->json([
                'success' => true,
                'data' => $team->load(['teamLead', 'members'])
            ]);
        } catch (\Exception $e) {
            Log::error('Team update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update team']
            ], 500);
        }
    }

    /**
     * Remove the specified team
     */
    public function destroy(Team $team): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            if ($team->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied: Team belongs to different tenant']
                ], 403);
            }

            $team->delete();

            return response()->json([
                'success' => true,
                'message' => 'Team deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Team destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to delete team']
            ], 500);
        }
    }

    /**
     * Get team statistics
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
                'total_teams' => Team::where('tenant_id', $user->tenant_id)->count(),
                'active_teams' => Team::where('tenant_id', $user->tenant_id)->where('is_active', true)->count(),
                'total_members' => User::where('tenant_id', $user->tenant_id)->count(),
                'teams_with_leads' => Team::where('tenant_id', $user->tenant_id)->whereNotNull('team_lead_id')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Team stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch team statistics']
            ], 500);
        }
    }

    /**
     * Invite user to team
     */
    public function invite(Request $request, Team $team): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            if ($team->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied: Team belongs to different tenant']
                ], 403);
            }

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'role' => 'nullable|string|max:255'
            ]);

            // Check if user belongs to same tenant
            $invitee = User::find($validated['user_id']);
            if ($invitee->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Cannot invite user from different tenant']
                ], 403);
            }

            // Add user to team (assuming there's a pivot table)
            $team->members()->syncWithoutDetaching([$validated['user_id'] => [
                'role' => $validated['role'] ?? 'member',
                'joined_at' => now()
            ]]);

            return response()->json([
                'success' => true,
                'message' => 'User invited to team successfully',
                'data' => $team->load(['teamLead', 'members'])
            ]);
        } catch (\Exception $e) {
            Log::error('Team invite error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to invite user to team']
            ], 500);
        }
    }
}
