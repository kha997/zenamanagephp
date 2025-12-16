<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\TenantMembersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * TenantInvitationsController
 * 
 * Handles tenant invitation management API endpoints.
 * All operations are scoped to the active tenant.
 */
class TenantInvitationsController extends BaseApiV1Controller
{
    public function __construct(
        private TenantMembersService $membersService
    ) {}

    /**
     * List invitations for the active tenant
     * GET /api/v1/app/tenant/invitations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();

            $invitations = $this->membersService->listInvitationsForTenant($tenantId);

            return $this->successResponse([
                'invitations' => $invitations->values()->all(),
            ]);
        } catch (\Exception $e) {
            $this->logError($e);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Create a new invitation
     * POST /api/v1/app/tenant/invitations
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => ['required', 'email'],
                'role' => [
                    'required',
                    'string',
                    Rule::in(array_keys(config('permissions.tenant_roles', []))),
                ],
            ]);

            $tenantId = $this->getTenantId();
            $user = auth()->user();

            $invitation = $this->membersService->createInvitation(
                $tenantId,
                $validated['email'],
                $validated['role'],
                $user
            );

            return $this->successResponse([
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'invited_by' => $invitation->inviter ? [
                    'id' => $invitation->inviter->id,
                    'name' => $invitation->inviter->name,
                ] : null,
                'created_at' => $invitation->created_at,
                'expires_at' => $invitation->expires_at,
            ], 'Invitation created successfully', 201);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $code = 'VALIDATION_FAILED';
            
            if (isset($errors['email'])) {
                $first = $errors['email'][0] ?? null;
                if ($first === 'TENANT_INVITE_ALREADY_MEMBER') {
                    $code = 'TENANT_INVITE_ALREADY_MEMBER';
                } elseif ($first === 'TENANT_INVITE_ALREADY_PENDING') {
                    $code = 'TENANT_INVITE_ALREADY_PENDING';
                }
            } elseif (isset($errors['role'])) {
                $first = $errors['role'][0] ?? null;
                if ($first === 'TENANT_INVALID_ROLE') {
                    $code = 'TENANT_INVALID_ROLE';
                }
            } elseif (isset($errors['permission'])) {
                $code = 'TENANT_PERMISSION_DENIED';
            }

            // TENANT_PERMISSION_DENIED should return 403, others return 422
            $status = $code === 'TENANT_PERMISSION_DENIED' ? 403 : 422;

            return $this->errorResponse(
                $e->getMessage(),
                $status,
                $errors,
                $code
            );
        } catch (\Exception $e) {
            $this->logError($e);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Revoke (cancel) an invitation
     * DELETE /api/v1/app/tenant/invitations/{invitation}
     */
    public function destroy(Request $request, string $invitation): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $user = auth()->user();

            $this->membersService->revokeInvitation($tenantId, $invitation, $user);

            return $this->successResponse(null, 'Invitation revoked successfully', 204);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $code = 'VALIDATION_FAILED';
            
            if (isset($errors['permission'])) {
                $code = 'TENANT_PERMISSION_DENIED';
            }

            // TENANT_PERMISSION_DENIED should return 403, others return 422
            $status = $code === 'TENANT_PERMISSION_DENIED' ? 403 : 422;

            return $this->errorResponse(
                $e->getMessage(),
                $status,
                $errors,
                $code
            );
        } catch (\Exception $e) {
            $this->logError($e);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Resend invitation email
     * POST /api/v1/app/tenant/invitations/{invitation}/resend
     */
    public function resend(Request $request, string $invitation): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $user = auth()->user();

            $invitationModel = $this->membersService->resendInvitation($tenantId, $invitation, $user);

            return $this->successResponse([
                'invitation' => [
                    'id' => $invitationModel->id,
                    'email' => $invitationModel->email,
                    'role' => $invitationModel->role,
                    'status' => $invitationModel->status,
                    'expires_at' => $invitationModel->expires_at,
                ],
            ], 'Invitation email resent successfully', 200);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $code = 'VALIDATION_FAILED';
            
            if (isset($errors['permission'])) {
                $code = 'TENANT_PERMISSION_DENIED';
                $status = 403;
            } elseif (isset($errors['invitation'])) {
                $first = $errors['invitation'][0] ?? null;
                if (in_array($first, [
                    'TENANT_INVITE_ALREADY_ACCEPTED',
                    'TENANT_INVITE_ALREADY_DECLINED',
                    'TENANT_INVITE_ALREADY_REVOKED',
                    'TENANT_INVITE_EXPIRED',
                    'TENANT_INVITE_INVALID_TOKEN',
                ], true)) {
                    $code = $first;
                    $status = 422;
                } else {
                    $status = 422;
                }
            } else {
                $status = 422;
            }

            return $this->errorResponse(
                $e->getMessage(),
                $status,
                $errors,
                $code
            );
        } catch (\Exception $e) {
            $this->logError($e);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}

