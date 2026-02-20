<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamInvitationService;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedInvitationAcceptController extends Controller
{
    public function __construct(private readonly TeamInvitationService $service)
    {
    }

    public function show(Request $request, string $token): ViewContract|ViewFactory
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(401);
        }

        $tenantId = (string) ($request->attributes->get('tenant_id') ?? $user->tenant_id ?? '');
        if ($tenantId === '') {
            abort(400, 'Tenant context missing');
        }

        $invitation = $this->service->resolveByToken($tenantId, null, $token);
        if ($invitation === null || (string) ($invitation->tenant_id ?? '') !== $tenantId) {
            abort(404);
        }

        $invitedUserId = (string) data_get($invitation->getAttributes(), 'invited_user_id', '');
        if ($invitedUserId !== '') {
            if (!hash_equals($invitedUserId, $user->id)) {
                abort(404);
            }
        } elseif (strcasecmp((string) $user->email, (string) $invitation->email) !== 0) {
            abort(404);
        }

        $teamId = (string) $request->query('team', (string) ($invitation->team_id ?? ''));
        $team = null;
        if ($teamId !== '') {
            $team = Team::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($teamId)
                ->first();
        }

        $resolvedTeamId = $teamId;
        $resolvedTeamName = data_get($invitation->metadata ?? [], 'team_name', 'Team');
        if ($team !== null) {
            $resolvedTeamId = $team->id;
            $resolvedTeamName = $team->name;
        }

        return view('invitations.accept', [
            'invitation' => $invitation,
            'teamId' => $resolvedTeamId,
            'teamName' => $resolvedTeamName,
            'token' => $token,
        ]);
    }
}
