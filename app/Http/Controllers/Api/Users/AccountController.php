<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Services\SessionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Account Controller
 * 
 * Handles user account management operations
 */
class AccountController extends Controller
{
    public function __construct(
        private SessionService $sessionService
    ) {}

    /**
     * Delete current user account
     */
    public function delete(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            // Revoke all sessions
            $this->sessionService->revokeAllSessions($user);

            // Soft delete user account
            $user->delete();

            Log::info('Account deleted', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::success([
                'message' => 'Account deleted successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete account', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to delete account',
                500,
                null,
                'ACCOUNT_DELETE_FAILED'
            );
        }
    }

    /**
     * Get user sessions
     */
    public function getSessions(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            $sessions = $this->sessionService->getUserSessions($user);

            return ApiResponse::success([
                'sessions' => $sessions->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'ip_address' => $session->ip_address,
                        'user_agent' => $session->user_agent ?? null,
                        'last_activity' => $session->last_activity_at,
                        'expires_at' => $session->expires_at,
                        'created_at' => $session->created_at,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sessions', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to get sessions',
                500,
                null,
                'SESSIONS_GET_FAILED'
            );
        }
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(Request $request, string $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            $revoked = $this->sessionService->revokeSession($user, $id);

            if (!$revoked) {
                return ApiResponse::error(
                    'Session not found',
                    404,
                    null,
                    'SESSION_NOT_FOUND'
                );
            }

            return ApiResponse::success([
                'message' => 'Session revoked successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to revoke session', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'session_id' => $id,
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to revoke session',
                500,
                null,
                'SESSION_REVOKE_FAILED'
            );
        }
    }

    /**
     * Revoke all sessions
     */
    public function revokeAllSessions(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            $count = $this->sessionService->revokeAllSessions($user);

            return ApiResponse::success([
                'message' => "All sessions revoked successfully. {$count} session(s) revoked.",
                'sessions_revoked' => $count
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to revoke all sessions', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to revoke all sessions',
                500,
                null,
                'SESSIONS_REVOKE_ALL_FAILED'
            );
        }
    }
}

