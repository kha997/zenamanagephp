<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EventRecord;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityFeedPageController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $query = EventRecord::query()
            ->where('tenant_id', $tenantId)
            ->with('project:id,tenant_id,name,code', 'actor:id,name');

        if ($request->filled('project_id')) {
            $query->where('project_id', (string) $request->query('project_id'));
        }

        if ($request->filled('event_key')) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], (string) $request->query('event_key'));
            $query->where('event_key', 'like', '%' . $escaped . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', (string) $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', (string) $request->query('date_to'));
        }

        $events = $query
            ->orderByDesc('occurred_at')
            ->paginate(50)
            ->withQueryString();

        return view('activity-feed.index', [
            'events' => $events,
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }
}
