<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\EventRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventRecordController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = EventRecord::where('tenant_id', (string) $user->tenant_id);

        if ($request->filled('event_key')) {
            $query->where('event_key', $request->input('event_key'));
        }

        if ($request->filled('aggregate_type')) {
            $query->where('aggregate_type', $request->input('aggregate_type'));
        }

        if ($request->filled('aggregate_id')) {
            $query->where('aggregate_id', $request->input('aggregate_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        $records = $query->orderByDesc('occurred_at')->paginate($perPage);

        return $this->successResponse([
            'items' => $records->items(),
            'pagination' => [
                'page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ], 'Event records retrieved successfully');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        $record = EventRecord::where('tenant_id', (string) $user->tenant_id)->find($id);

        if ($record === null) {
            return $this->notFound('Event record not found');
        }

        return $this->successResponse($record, 'Event record retrieved successfully');
    }
}
