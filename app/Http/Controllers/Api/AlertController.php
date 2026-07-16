<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\DashboardAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlertController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = DashboardAlert::where('user_id', (string) $user->id)
            ->where('tenant_id', (string) $user->tenant_id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->has('is_read')) {
            $query->where('is_read', (bool) $request->input('is_read'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        $alerts = $query->orderByDesc('triggered_at')->paginate($perPage);

        return $this->successResponse([
            'items' => $alerts->items(),
            'pagination' => [
                'page' => $alerts->currentPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
                'last_page' => $alerts->lastPage(),
            ],
        ], 'Alerts retrieved successfully');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        $alert = DashboardAlert::where('user_id', (string) $user->id)
            ->where('tenant_id', (string) $user->tenant_id)
            ->find($id);

        if ($alert === null) {
            return $this->notFound('Alert not found');
        }

        return $this->successResponse($alert, 'Alert retrieved successfully');
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        $alert = DashboardAlert::where('user_id', (string) $user->id)
            ->where('tenant_id', (string) $user->tenant_id)
            ->find($id);

        if ($alert === null) {
            return $this->notFound('Alert not found');
        }

        $alert->markAsRead();

        return $this->successResponse($alert->fresh(), 'Alert marked as read');
    }
}
