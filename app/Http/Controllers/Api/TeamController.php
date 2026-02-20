<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\ErrorEnvelopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $query = Team::query()
            ->where('tenant_id', $tenantId)
            ->with(['teamLead:id,name,email']);

        if ($request->filled('department')) {
            $query->where('department', (string) $request->input('department'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $teams = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $teams,
            'message' => 'Teams retrieved successfully',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return ErrorEnvelopeService::authenticationError();
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'department' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(Team::VALID_STATUSES)],
            'team_lead_id' => [
                'nullable',
                'string',
                Rule::exists('users', 'id')->where(static fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        $team = Team::query()->create([
            'tenant_id' => $tenantId,
            'name' => (string) $validated['name'],
            'description' => $validated['description'] ?? null,
            'department' => $validated['department'] ?? null,
            'status' => (string) ($validated['status'] ?? Team::STATUS_ACTIVE),
            'is_active' => ($validated['status'] ?? Team::STATUS_ACTIVE) !== Team::STATUS_ARCHIVED,
            'team_lead_id' => $validated['team_lead_id'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $team->load('teamLead:id,name,email'),
            'message' => 'Team created successfully',
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $team = Team::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->with(['teamLead:id,name,email', 'creator:id,name,email'])
            ->first();

        if (!$team instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $team,
            'message' => 'Team retrieved successfully',
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return ErrorEnvelopeService::authenticationError();
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $team = Team::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$team instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('teams', 'name')->ignore($team->id)->where(static fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(Team::VALID_STATUSES)],
            'team_lead_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('users', 'id')->where(static fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updates = $validated;
        $updates['updated_by'] = $user->id;

        if (array_key_exists('status', $updates)) {
            $updates['is_active'] = $updates['status'] !== Team::STATUS_ARCHIVED;
        }

        $team->update($updates);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $team->fresh()->load('teamLead:id,name,email'),
            'message' => 'Team updated successfully',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $team = Team::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$team instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $team->delete();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Team deleted successfully',
        ]);
    }

    public function addMember(Request $request, string $team): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $model = $this->teamForTenant($tenantId, $team);
        if (!$model instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $validated = $request->validate([
            'user_id' => [
                'required',
                'string',
                Rule::exists('users', 'id')->where(static fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'role' => ['sometimes', Rule::in(Team::VALID_ROLES)],
        ]);

        $alreadyMember = DB::table('team_members')
            ->where('team_id', $model->id)
            ->where('user_id', $validated['user_id'])
            ->whereNull('left_at')
            ->exists();
        if ($alreadyMember) {
            return ErrorEnvelopeService::conflictError('User is already an active team member');
        }

        $model->members()->syncWithoutDetaching([
            $validated['user_id'] => [
                'role' => $validated['role'] ?? Team::ROLE_MEMBER,
                'joined_at' => now(),
                'left_at' => null,
            ],
        ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Member added successfully',
        ], 201);
    }

    public function removeMember(Request $request, string $team): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $model = $this->teamForTenant($tenantId, $team);
        if (!$model instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $validated = $request->validate([
            'user_id' => [
                'required',
                'string',
                Rule::exists('users', 'id')->where(static fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        $memberExists = DB::table('team_members')
            ->where('team_id', $model->id)
            ->where('user_id', $validated['user_id'])
            ->exists();
        if (!$memberExists) {
            return ErrorEnvelopeService::notFoundError('Team member');
        }

        $model->members()->updateExistingPivot($validated['user_id'], [
            'left_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Member removed successfully',
        ]);
    }

    public function updateMemberRole(Request $request, string $team): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $model = $this->teamForTenant($tenantId, $team);
        if (!$model instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $validated = $request->validate([
            'user_id' => [
                'required',
                'string',
                Rule::exists('users', 'id')->where(static fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'role' => ['required', Rule::in(Team::VALID_ROLES)],
        ]);

        $memberExists = DB::table('team_members')
            ->where('team_id', $model->id)
            ->where('user_id', $validated['user_id'])
            ->exists();
        if (!$memberExists) {
            return ErrorEnvelopeService::notFoundError('Team member');
        }

        $model->members()->updateExistingPivot($validated['user_id'], [
            'role' => $validated['role'],
            'left_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Member role updated successfully',
        ]);
    }

    public function getMembers(Request $request, string $team): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $model = $this->teamForTenant($tenantId, $team);
        if (!$model instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $members = DB::table('team_members')
            ->join('users', 'users.id', '=', 'team_members.user_id')
            ->where('team_members.team_id', $model->id)
            ->whereNull('team_members.left_at')
            ->get();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $members,
            'message' => 'Team members retrieved successfully',
        ]);
    }

    public function getStatistics(Request $request, string $team): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $model = $this->teamForTenant($tenantId, $team);
        if (!$model instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $model->getStatistics(),
            'message' => 'Team statistics retrieved successfully',
        ]);
    }

    public function archive(Request $request, string $team): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $model = $this->teamForTenant($tenantId, $team);
        if (!$model instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $model->update([
            'status' => Team::STATUS_ARCHIVED,
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Team archived successfully',
        ]);
    }

    public function restore(Request $request, string $team): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $model = $this->teamForTenant($tenantId, $team);
        if (!$model instanceof Team) {
            return ErrorEnvelopeService::notFoundError('Team');
        }

        $model->update([
            'status' => Team::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Team restored successfully',
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
