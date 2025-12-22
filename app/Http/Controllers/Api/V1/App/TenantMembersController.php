<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\TenantMembersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * TenantMembersController
 * 
 * Handles tenant member management API endpoints.
 * All operations are scoped to the active tenant.
 */
class TenantMembersController extends BaseApiV1Controller
{
    public function __construct(
        private TenantMembersService $membersService
    ) {}

    /**
     * List members of the active tenant
     * GET /api/v1/app/tenant/members
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();

            $filters = [
                'search' => $request->input('search'),
                'role' => $request->input('role'),
                'per_page' => $request->input('per_page'),
                'page' => $request->input('page'),
            ];

            $members = $this->membersService->listMembersForTenant($tenantId, $filters);

            return $this->successResponse([
                'members' => $members instanceof \Illuminate\Pagination\LengthAwarePaginator 
                    ? $members->items() 
                    : $members->values()->all(),
                'pagination' => $members instanceof \Illuminate\Pagination\LengthAwarePaginator ? [
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                    'per_page' => $members->perPage(),
                    'total' => $members->total(),
                    'from' => $members->firstItem(),
                    'to' => $members->lastItem(),
                ] : null,
            ]);
        } catch (\Exception $e) {
            $this->logError($e);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update member role
     * PATCH /api/v1/app/tenant/members/{member}
     */
    public function update(Request $request, string $member): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role' => [
                    'required',
                    'string',
                    Rule::in(array_keys(config('permissions.tenant_roles', []))),
                ],
            ]);

            $tenantId = $this->getTenantId();
            $user = auth()->user();

            $updatedUser = $this->membersService->updateMemberRole(
                $tenantId,
                $member,
                $validated['role'],
                $user
            );

            // Get updated member data
            $pivot = DB::table('user_tenants')
                ->where('user_id', $updatedUser->id)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->first();

            return $this->successResponse([
                'id' => $updatedUser->id,
                'name' => $updatedUser->name,
                'email' => $updatedUser->email,
                'role' => $pivot->role ?? null,
                'is_default' => (bool) ($pivot->is_default ?? false),
                'joined_at' => $pivot->created_at ?? null,
            ]);
        } catch (ValidationException $e) {
            // Map validation errors to error codes
            $errors = $e->errors();
            $code = 'VALIDATION_FAILED';
            
            if (isset($errors['member'])) {
                $first = $errors['member'][0] ?? null;
                // Check if it's about last owner protection
                if ($first && (str_contains($first, 'last owner') || str_contains($first, 'Cannot remove or demote'))) {
                    $code = 'TENANT_LAST_OWNER_PROTECTED';
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
     * Remove member from tenant
     * DELETE /api/v1/app/tenant/members/{member}
     */
    public function destroy(Request $request, string $member): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $user = auth()->user();

            $this->membersService->removeMember($tenantId, $member, $user);

            return $this->successResponse(null, 'Member removed successfully', 204);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $code = 'VALIDATION_FAILED';
            
            if (isset($errors['member'])) {
                $first = $errors['member'][0] ?? null;
                // Check if it's about last owner protection
                if ($first && (str_contains($first, 'last owner') || str_contains($first, 'Cannot remove or demote'))) {
                    $code = 'TENANT_LAST_OWNER_PROTECTED';
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
     * Promote member to owner (or transfer ownership)
     * POST /api/v1/app/tenant/members/{member}/make-owner
     */
    public function makeOwner(Request $request, string $member): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $user = auth()->user();

            $demoteSelf = $request->boolean('demote_self', false);

            $result = $this->membersService->promoteMemberToOwner(
                $tenantId,
                $member,
                $user,
                $demoteSelf
            );

            return $this->successResponse([
                'member' => $result['target_member'],
                'acting_member' => $result['acting_member'],
            ], 'Member promoted to owner successfully');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $code = 'VALIDATION_FAILED';
            $status = 422;

            if (isset($errors['permission'])) {
                $code = 'TENANT_PERMISSION_DENIED';
                $status = 403;
            } elseif (isset($errors['owner'])) {
                $first = $errors['owner'][0] ?? null;
                if ($first && str_contains($first, 'already an owner')) {
                    $code = 'TENANT_MEMBER_ALREADY_OWNER';
                }
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

    /**
     * Self-service: Leave current tenant
     * POST /api/v1/app/tenant/leave
     */
    public function leaveSelf(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $user = auth()->user();

            $this->membersService->selfLeaveTenant($tenantId, $user);

            return $this->successResponse(null, 'Left tenant successfully', 204);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $code = 'VALIDATION_FAILED';
            
            if (isset($errors['member'])) {
                $first = $errors['member'][0] ?? null;
                // Check if it's about last owner protection
                if ($first && (str_contains($first, 'last owner') || str_contains($first, 'Cannot remove or demote'))) {
                    $code = 'TENANT_LAST_OWNER_PROTECTED';
                }
            }

            // Self-leave does not require tenant.manage_members permission,
            // so we don't check for TENANT_PERMISSION_DENIED here
            // All errors return 422 (validation errors)
            return $this->errorResponse(
                $e->getMessage(),
                422,
                $errors,
                $code
            );
        } catch (\Exception $e) {
            $this->logError($e);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}

