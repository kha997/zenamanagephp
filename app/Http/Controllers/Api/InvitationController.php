<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use App\Services\ErrorEnvelopeService;
use App\Services\TeamInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvitationController extends Controller
{
    public function __construct(private readonly TeamInvitationService $service)
    {
    }

    public function index(Request $request, string $team): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $teamModel = $this->teamForTenant($tenantId, $team);
        if (!$teamModel instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $invitations = Invitation::query()
            ->where('tenant_id', $tenantId)
            ->where('team_id', $teamModel->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $invitations,
            'message' => 'Invitations retrieved successfully',
        ]);
    }

    public function store(Request $request, string $team): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return ErrorEnvelopeService::authenticationError();
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $teamModel = $this->teamForTenant($tenantId, $team);
        if (!$teamModel instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['nullable', 'string', 'in:member,lead,admin'],
            'message' => ['nullable', 'string', 'max:5000'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        try {
            $invitation = $this->service->create(
                $teamModel,
                $user,
                strtolower((string) $validated['email']),
                (string) ($validated['role'] ?? Team::ROLE_MEMBER),
                $validated['message'] ?? null,
                isset($validated['expires_in_days']) ? (int) $validated['expires_in_days'] : null,
            );
        } catch (\DomainException $exception) {
            return ErrorEnvelopeService::conflictError($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'id' => $invitation->id,
                'token' => $invitation->token,
                'email' => $invitation->email,
                'status' => $invitation->status,
                'team_id' => $invitation->team_id,
                'expires_at' => optional($invitation->expires_at)?->toISOString(),
            ],
            'message' => 'Invitation created successfully',
        ], 201);
    }

    public function revoke(Request $request, string $team, string $invitation): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return ErrorEnvelopeService::authenticationError();
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $teamModel = $this->teamForTenant($tenantId, $team);
        if (!$teamModel instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $invitationModel = Invitation::query()
            ->where('tenant_id', $tenantId)
            ->where('team_id', $teamModel->id)
            ->whereKey($invitation)
            ->first();

        if (!$invitationModel instanceof Invitation) {
            return ErrorEnvelopeService::notFoundError('Invitation');
        }

        try {
            $updated = $this->service->revoke($invitationModel, $user);
        } catch (\DomainException $exception) {
            return ErrorEnvelopeService::conflictError($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'id' => $updated->id,
                'status' => $updated->status,
                'revoked_at' => optional($updated->revoked_at)?->toISOString(),
            ],
            'message' => 'Invitation revoked successfully',
        ]);
    }

    public function accept(Request $request, string $team, string $token): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return ErrorEnvelopeService::authenticationError();
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $teamModel = $this->teamForTenant($tenantId, $team);
        if (!$teamModel instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $invitationModel = $this->service->resolveByToken($tenantId, $teamModel->id, $token);

        if (!$invitationModel instanceof Invitation) {
            return ErrorEnvelopeService::notFoundError('Invitation');
        }

        try {
            $accepted = $this->service->accept($invitationModel, $user);
        } catch (\InvalidArgumentException $exception) {
            return ErrorEnvelopeService::authorizationError($exception->getMessage());
        } catch (\DomainException $exception) {
            return ErrorEnvelopeService::conflictError($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'id' => $accepted->id,
                'status' => $accepted->status,
                'accepted_at' => optional($accepted->accepted_at)?->toISOString(),
                'accepted_by_user_id' => $accepted->accepted_by_user_id,
            ],
            'message' => 'Invitation accepted successfully',
        ]);
    }

    private function tenantId(Request $request): string
    {
        $authTenantId = data_get(Auth::user(), 'tenant_id');
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? $authTenantId;

        return $tenantId ? (string) $tenantId : '';
    }

    private function teamForTenant(string $tenantId, string $teamId): ?Team
    {
        return Team::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($teamId)
            ->first();
    }
}
