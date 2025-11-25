<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\TenantInvitation;
use App\Services\TenantInvitationLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * TenantInvitationLifecycleController
 * 
 * Handles tenant invitation lifecycle operations from invitee perspective:
 * - Preview invitation (public, no auth)
 * - Accept invitation (requires auth)
 * - Decline invitation (requires auth)
 * 
 * Round 20: Tenant Invitation Lifecycle (Accept/Decline)
 */
class TenantInvitationLifecycleController extends BaseApiV1Controller
{
    public function __construct(
        private TenantInvitationLifecycleService $lifecycleService
    ) {}

    /**
     * Show public invitation preview by token
     * GET /api/v1/tenant/invitations/{token}
     * 
     * Public endpoint - no authentication required
     * Returns minimal invitation metadata for landing page rendering
     */
    public function showPublic(string $token): JsonResponse
    {
        try {
            $invitation = $this->lifecycleService->getInvitationForToken($token);

            // Check if expired (auto-update status if needed)
            $isExpired = $invitation->isExpired();
            if ($isExpired && $invitation->status === TenantInvitation::STATUS_PENDING) {
                $invitation->status = TenantInvitation::STATUS_EXPIRED;
                $invitation->save();
            }

            // Load tenant relationship if not loaded
            if (!$invitation->relationLoaded('tenant')) {
                $invitation->load('tenant:id,name');
            }

            return $this->successResponse([
                'tenant_name' => $invitation->tenant->name ?? 'Unknown Tenant',
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'is_expired' => $isExpired,
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $code = 'TENANT_INVITE_INVALID_TOKEN';
            
            // Extract error code from validation errors
            foreach ($errors as $field => $messages) {
                if (is_array($messages) && !empty($messages)) {
                    $firstMessage = $messages[0];
                    if (str_starts_with($firstMessage, 'TENANT_INVITE_')) {
                        $code = $firstMessage;
                        break;
                    }
                }
            }
            
            // Use ErrorEnvelopeService directly to pass custom code for 404
            return \App\Services\ErrorEnvelopeService::error(
                $code,
                $e->getMessage(),
                $errors,
                404
            );
        } catch (\Exception $e) {
            $this->logError($e);
            return $this->errorResponse('Failed to retrieve invitation', 500);
        }
    }

    /**
     * Accept invitation by token
     * POST /api/v1/tenant/invitations/{token}/accept
     * 
     * Requires authentication (auth:sanctum)
     * Attaches user to tenant if valid
     */
    public function accept(string $token, Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401, null, 'UNAUTHORIZED');
            }

            // Check if user was already a member (before accepting)
            $invitation = $this->lifecycleService->getInvitationForToken($token);
            $alreadyMember = \App\Models\UserTenant::where('tenant_id', $invitation->tenant_id)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->exists();

            // Accept the invitation
            $invitation = $this->lifecycleService->acceptInvitationByToken($token, $user);

            // Load tenant relationship
            $invitation->load('tenant:id,name');

            return $this->successResponse([
                'tenant' => [
                    'id' => $invitation->tenant->id,
                    'name' => $invitation->tenant->name,
                ],
                'invitation_status' => $invitation->status,
                'already_member' => $alreadyMember,
            ], 'Invitation accepted successfully');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $code = 'VALIDATION_FAILED';
            
            // Extract error code from validation errors
            foreach ($errors as $field => $messages) {
                if (is_array($messages) && !empty($messages)) {
                    $firstMessage = $messages[0];
                    if (str_starts_with($firstMessage, 'TENANT_INVITE_')) {
                        $code = $firstMessage;
                        break;
                    }
                }
            }
            
            // Map error codes to HTTP status codes
            $statusCode = match($code) {
                'TENANT_INVITE_INVALID_TOKEN' => 404,
                'TENANT_INVITE_EXPIRED',
                'TENANT_INVITE_ALREADY_ACCEPTED',
                'TENANT_INVITE_ALREADY_REVOKED',
                'TENANT_INVITE_ALREADY_DECLINED',
                'TENANT_INVITE_EMAIL_MISMATCH' => 422,
                default => 422,
            };

            // Use ErrorEnvelopeService directly for 404 to pass custom code
            if ($statusCode === 404) {
                return \App\Services\ErrorEnvelopeService::error(
                    $code,
                    $e->getMessage(),
                    $errors,
                    $statusCode
                );
            }

            return $this->errorResponse(
                $e->getMessage(),
                $statusCode,
                $errors,
                $code
            );
        } catch (\Exception $e) {
            $this->logError($e);
            return $this->errorResponse('Failed to accept invitation', 500);
        }
    }

    /**
     * Decline invitation by token
     * POST /api/v1/tenant/invitations/{token}/decline
     * 
     * Requires authentication (auth:sanctum)
     */
    public function decline(string $token, Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401, null, 'UNAUTHORIZED');
            }

            $invitation = $this->lifecycleService->declineInvitationByToken($token, $user);

            return $this->successResponse([
                'invitation_status' => $invitation->status,
            ], 'Invitation declined successfully');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $code = 'VALIDATION_FAILED';
            
            // Extract error code from validation errors
            foreach ($errors as $field => $messages) {
                if (is_array($messages) && !empty($messages)) {
                    $firstMessage = $messages[0];
                    if (str_starts_with($firstMessage, 'TENANT_INVITE_')) {
                        $code = $firstMessage;
                        break;
                    }
                }
            }
            
            // Map error codes to HTTP status codes
            $statusCode = match($code) {
                'TENANT_INVITE_INVALID_TOKEN' => 404,
                'TENANT_INVITE_EXPIRED',
                'TENANT_INVITE_ALREADY_ACCEPTED',
                'TENANT_INVITE_ALREADY_REVOKED',
                'TENANT_INVITE_ALREADY_DECLINED' => 422,
                default => 422,
            };

            // Use ErrorEnvelopeService directly for 404 to pass custom code
            if ($statusCode === 404) {
                return \App\Services\ErrorEnvelopeService::error(
                    $code,
                    $e->getMessage(),
                    $errors,
                    $statusCode
                );
            }

            return $this->errorResponse(
                $e->getMessage(),
                $statusCode,
                $errors,
                $code
            );
        } catch (\Exception $e) {
            $this->logError($e);
            return $this->errorResponse('Failed to decline invitation', 500);
        }
    }
}

