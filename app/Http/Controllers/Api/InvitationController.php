<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInvitationRequest;
use App\Http\Requests\BulkInvitationRequest;
use App\Services\InvitationService;
use App\Models\Invitation;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * InvitationController - API endpoints for invitation management
 */
class InvitationController extends Controller
{
    private InvitationService $invitationService;

    public function __construct(InvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * Create a single invitation
     * POST /api/admin/invitations
     */
    public function store(CreateInvitationRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return ApiResponse::unauthorized('Authentication required');
            }

            // Check authorization via policy
            $this->authorize('create', Invitation::class);
            
            $data = $request->validated();
            
            // Additional tenant access check after validation
            $tenantId = $data['tenant_id'] ?? null;
            if ($tenantId && !$user->hasPermission('admin.access')) {
                // Convert both to string for comparison
                $tenantIdString = (string) $tenantId;
                $userTenantIdString = $user->tenant_id ? (string) $user->tenant_id : null;
                if ($tenantIdString !== $userTenantIdString) {
                    return ApiResponse::forbidden('You can only invite users to your own tenant');
                }
            }
            
            $data['invited_by'] = $user->id;
            $data['send_email'] = $request->input('send_email', true);

            // Set expires_at if provided
            if (!empty($data['expires_in_days'])) {
                $data['expires_at'] = now()->addDays($data['expires_in_days']);
                unset($data['expires_in_days']);
            }

            $result = $this->invitationService->createInvitation($data);

            // Format response based on result status
            if ($result['status'] === 'created') {
                $invitation = $result['invitation'];
                return ApiResponse::success([
                    'invitation' => [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'token' => $invitation->token,
                        'link' => url("/invite/{$invitation->token}"),
                        'expires_at' => $invitation->expires_at?->toISOString() ?? null,
                        'status' => $invitation->status,
                    ],
                    'email_sent' => $data['send_email'] && $this->invitationService->isEmailConfigured(),
                ], 'Invitation created successfully', 201);
            } elseif ($result['status'] === 'already_member') {
                return ApiResponse::error($result['message'], 409);
            } elseif ($result['status'] === 'pending_invitation') {
                $invitation = $result['invitation'];
                return ApiResponse::success([
                    'invitation' => [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'token' => $invitation->token,
                        'link' => url("/invite/{$invitation->token}"),
                        'expires_at' => $invitation->expires_at?->toISOString() ?? null,
                        'status' => $invitation->status,
                    ],
                    'message' => $result['message'],
                ], 'Pending invitation already exists', 200);
            }

            return ApiResponse::error('Failed to create invitation', 500);
        } catch (AuthorizationException $e) {
            Log::warning('InvitationController::store authorization denied', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::forbidden('You do not have permission to create invitations');
        } catch (\Exception $e) {
            Log::error('InvitationController::store error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::error('Failed to create invitation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create bulk invitations
     * POST /api/admin/invitations/bulk
     */
    public function bulkStore(BulkInvitationRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return ApiResponse::unauthorized('Authentication required');
            }

            // Check authorization via policy
            $this->authorize('create', Invitation::class);
            
            $data = $request->validated();
            
            // Additional tenant access check after validation
            $tenantId = $data['tenant_id'] ?? null;
            if ($tenantId && !$user->hasPermission('admin.access')) {
                // Convert both to string for comparison
                $tenantIdString = (string) $tenantId;
                $userTenantIdString = $user->tenant_id ? (string) $user->tenant_id : null;
                if ($tenantIdString !== $userTenantIdString) {
                    return ApiResponse::forbidden('You can only invite users to your own tenant');
                }
            }
            $emails = $data['emails'];
            unset($data['emails']);

            $defaults = array_merge($data, [
                'invited_by' => $user->id,
                'send_email' => $request->input('send_email', true),
            ]);

            // Set expires_at if provided
            if (!empty($defaults['expires_in_days'])) {
                $defaults['expires_at'] = now()->addDays($defaults['expires_in_days']);
                unset($defaults['expires_in_days']);
            }

            $result = $this->invitationService->createBulkInvitations($emails, $defaults);

            // Format invitations with links
            $formattedCreated = collect($result['created'])->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'token' => $invitation->token,
                    'link' => url("/invite/{$invitation->token}"),
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'status' => $invitation->status,
                ];
            })->toArray();

            $formattedPending = collect($result['pending'])->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'token' => $invitation->token,
                    'link' => url("/invite/{$invitation->token}"),
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'status' => $invitation->status,
                ];
            })->toArray();

            return ApiResponse::success([
                'created' => $formattedCreated,
                'already_member' => $result['already_member'],
                'pending' => $formattedPending,
                'errors' => $result['errors'],
                'summary' => [
                    'total' => count($emails),
                    'created' => count($result['created']),
                    'already_member' => count($result['already_member']),
                    'pending' => count($result['pending']),
                    'errors' => count($result['errors']),
                ],
                'email_sent' => $defaults['send_email'] && $this->invitationService->isEmailConfigured(),
            ], 'Bulk invitations processed', 201);
        } catch (AuthorizationException $e) {
            Log::warning('InvitationController::bulkStore authorization denied', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::forbidden('You do not have permission to create invitations');
        } catch (\Exception $e) {
            Log::error('InvitationController::bulkStore error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::error('Failed to create bulk invitations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List invitations with filters
     * GET /api/admin/invitations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return ApiResponse::unauthorized('Authentication required');
            }

            // Check authorization
            $this->authorize('viewAny', Invitation::class);

            $query = Invitation::with(['tenant', 'inviter', 'project']);

            // Apply tenant filter
            if ($user->hasPermission('admin.access')) {
                // Super Admin can see all tenants
                if ($request->has('tenant_id')) {
                    $query->where('tenant_id', $request->input('tenant_id'));
                }
            } else {
                // Org Admin can only see their tenant
                $query->where('tenant_id', $user->tenant_id);
            }

            // Apply status filter
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Apply search filter
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = min((int) $request->input('per_page', 15), 100);
            $invitations = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Format response
            $formattedInvitations = collect($invitations->items())->map(function ($invitation) {
                try {
                    return [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'first_name' => $invitation->first_name,
                        'last_name' => $invitation->last_name,
                        'role' => $invitation->role,
                        'tenant_id' => $invitation->tenant_id,
                        'tenant_name' => $invitation->tenant?->name ?? 'N/A',
                        'token' => $invitation->token,
                        'link' => url("/invite/{$invitation->token}"),
                        'status' => $invitation->status,
                        'expires_at' => $invitation->expires_at?->toISOString() ?? null,
                        'used_at' => $invitation->used_at?->toISOString() ?? null,
                        'accepted_at' => $invitation->accepted_at?->toISOString() ?? null,
                        'invited_by' => $invitation->invited_by,
                        'inviter_name' => $invitation->inviter?->name ?? 'N/A',
                        'created_at' => $invitation->created_at?->toISOString() ?? null,
                    ];
                } catch (\Exception $e) {
                    Log::error('Error formatting invitation', [
                        'invitation_id' => $invitation->id,
                        'error' => $e->getMessage(),
                    ]);
                    return null;
                }
            })->filter()->values()->toArray();

            return ApiResponse::success([
                'data' => $formattedInvitations,
                'meta' => [
                    'current_page' => $invitations->currentPage(),
                    'last_page' => $invitations->lastPage(),
                    'per_page' => $invitations->perPage(),
                    'total' => $invitations->total(),
                ],
                'links' => [
                    'first' => $invitations->url(1),
                    'last' => $invitations->url($invitations->lastPage()),
                    'prev' => $invitations->previousPageUrl(),
                    'next' => $invitations->nextPageUrl(),
                ],
            ]);
        } catch (AuthorizationException $e) {
            Log::warning('InvitationController::index authorization denied', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::forbidden('You do not have permission to view invitations');
        } catch (\Exception $e) {
            Log::error('InvitationController::index error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::error('Failed to fetch invitations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Resend invitation
     * POST /api/admin/invitations/{id}/resend
     */
    public function resend(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return ApiResponse::unauthorized('Authentication required');
            }

            $invitation = Invitation::findOrFail($id);

            // Check authorization
            $this->authorize('update', $invitation);

            $invitation = $this->invitationService->resendInvitation($id);

            return ApiResponse::success([
                'invitation' => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'token' => $invitation->token,
                    'link' => url("/invite/{$invitation->token}"),
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'status' => $invitation->status,
                ],
                'email_sent' => $this->invitationService->isEmailConfigured(),
            ], 'Invitation resent successfully');
        } catch (AuthorizationException $e) {
            Log::warning('InvitationController::resend authorization denied', [
                'error' => $e->getMessage(),
                'invitation_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::forbidden('You do not have permission to resend this invitation');
        } catch (\Exception $e) {
            Log::error('InvitationController::resend error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'invitation_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::error('Failed to resend invitation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate invitation token (public endpoint)
     * GET /api/invitations/{token}/validate
     */
    public function validateToken(Request $request, string $token): JsonResponse
    {
        try {
            // Rate limit by IP for token validation
            $ipKey = 'invitation_validate:' . $request->ip();
            if (RateLimiter::tooManyAttempts($ipKey, 10)) {
                $retryAfter = RateLimiter::availableIn($ipKey);
                $response = ApiResponse::error('Too many validation attempts. Please try again later.', 429);
                $response->headers->set('Retry-After', (string) $retryAfter);
                return $response;
            }
            RateLimiter::hit($ipKey, 60); // 10 attempts per minute

            $invitation = Invitation::findByToken($token);

            if (!$invitation) {
                // Generic error message to prevent information leakage
                return ApiResponse::error('Invalid invitation token', 404);
            }

            if (!$invitation->canBeAccepted()) {
                if ($invitation->is_expired) {
                    return ApiResponse::error('Invitation has expired', 410);
                }
                if ($invitation->is_used) {
                    return ApiResponse::error('Invitation has already been used', 410);
                }
                return ApiResponse::error('Invitation cannot be accepted', 400);
            }

            return ApiResponse::success([
                'valid' => true,
                'email' => $invitation->email,
                'first_name' => $invitation->first_name,
                'last_name' => $invitation->last_name,
                'role' => $invitation->role,
                'tenant_id' => $invitation->tenant_id,
                'tenant_name' => $invitation->tenant?->name ?? 'N/A',
                'expires_at' => $invitation->expires_at->toISOString(),
                'message' => $invitation->message,
            ]);
        } catch (\Exception $e) {
            Log::error('InvitationController::validateToken error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 8) . '...', // Log partial token only
            ]);

            return ApiResponse::error('Failed to validate invitation', 500);
        }
    }

    /**
     * Accept invitation (public endpoint)
     * POST /api/invitations/{token}/accept
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        try {
            // Rate limit by IP and email for acceptance
            $ipKey = 'invitation_accept_ip:' . $request->ip();
            if (RateLimiter::tooManyAttempts($ipKey, 5)) {
                $retryAfter = RateLimiter::availableIn($ipKey);
                $response = ApiResponse::error('Too many acceptance attempts. Please try again later.', 429);
                $response->headers->set('Retry-After', (string) $retryAfter);
                return $response;
            }
            RateLimiter::hit($ipKey, 300); // 5 attempts per 5 minutes

            $invitation = Invitation::findByToken($token);
            
            // Additional rate limit by email
            if ($invitation) {
                $emailKey = 'invitation_accept_email:' . $invitation->email;
                if (RateLimiter::tooManyAttempts($emailKey, 3)) {
                    $retryAfter = RateLimiter::availableIn($emailKey);
                    $response = ApiResponse::error('Too many attempts for this invitation. Please contact support.', 429);
                    $response->headers->set('Retry-After', (string) $retryAfter);
                    return $response;
                }
                RateLimiter::hit($emailKey, 600); // 3 attempts per 10 minutes per email
            }

            if (!$invitation) {
                return ApiResponse::error('Invalid invitation token', 404);
            }

            // Validate user data
            $validator = Validator::make($request->all(), [
                'name' => ['required_if:is_new_user,true', 'string', 'max:255'],
                'password' => ['required_if:is_new_user,true', 'string', 'min:8', 'confirmed'],
                'first_name' => ['nullable', 'string', 'max:255'],
                'last_name' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
                'job_title' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            // Check if user is logged in
            $user = Auth::user();
            $isNewUser = !$user;

            // If user is logged in, check if email matches
            if ($user && $user->email !== $invitation->email) {
                return ApiResponse::error('This invitation is for a different email address', 403);
            }

            $userData = $request->only(['name', 'password', 'first_name', 'last_name', 'phone', 'job_title']);
            if ($user) {
                $userData['name'] = $user->name;
            }

            $user = $this->invitationService->acceptInvitation($token, $userData);

            // Auto-login if new user
            if ($isNewUser) {
                Auth::login($user);
            }

            return ApiResponse::success([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                ],
                'message' => 'Invitation accepted successfully',
            ], 'Invitation accepted successfully', 201);
        } catch (\Exception $e) {
            Log::error('InvitationController::accept error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token' => substr($token, 0, 8) . '...',
            ]);

            // Check if it's a known error that should return 410
            if (str_contains($e->getMessage(), 'expired') || str_contains($e->getMessage(), 'already been used')) {
                return ApiResponse::error($e->getMessage(), 410);
            }

            return ApiResponse::error('Failed to accept invitation: ' . $e->getMessage(), 500);
        }
    }
}
