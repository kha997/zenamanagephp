<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Display the calendar page
     */
    public function index(Request $request): View
    {
        $tenant = app('tenant');
        
        // Get KPI data
        $kpis = [
            'todays_events' => 3, // TODO: Calculate from actual events
            'this_week_events' => 12, // TODO: Calculate from actual events
            'upcoming_events' => 25, // TODO: Calculate from actual events
            'total_events' => 45, // TODO: Calculate from actual events
        ];
        
        // Get calendar events for the current tenant
        $events = [
            [
                'id' => 'event_001',
                'title' => 'Project Kickoff Meeting',
                'start' => now()->addDays(1)->setTime(9, 0)->toISOString(),
                'end' => now()->addDays(1)->setTime(10, 30)->toISOString(),
                'type' => 'meeting',
                'project_id' => 'project_001',
                'attendees' => ['john.doe', 'jane.smith'],
            ],
            [
                'id' => 'event_002',
                'title' => 'Design Review Deadline',
                'start' => now()->addDays(3)->setTime(17, 0)->toISOString(),
                'end' => now()->addDays(3)->setTime(17, 0)->toISOString(),
                'type' => 'deadline',
                'project_id' => 'project_001',
                'attendees' => ['design.team'],
            ],
            [
                'id' => 'event_003',
                'title' => 'Site Inspection',
                'start' => now()->addDays(5)->setTime(8, 0)->toISOString(),
                'end' => now()->addDays(5)->setTime(12, 0)->toISOString(),
                'type' => 'milestone',
                'project_id' => 'project_002',
                'attendees' => ['site.engineer', 'project.manager'],
            ],
        ];

        // Get projects for event creation
        $projects = [
            ['id' => 'project_001', 'name' => 'Office Building'],
            ['id' => 'project_002', 'name' => 'Warehouse Construction'],
            ['id' => 'project_003', 'name' => 'Retail Center'],
        ];

        // Get team members for event assignment
        $teamMembers = [
            ['id' => 'user_001', 'name' => 'John Doe', 'role' => 'pm'],
            ['id' => 'user_002', 'name' => 'Jane Smith', 'role' => 'member'],
            ['id' => 'user_003', 'name' => 'Bob Johnson', 'role' => 'member'],
        ];

        return view('app.calendar.index', compact('kpis', 'events', 'projects', 'teamMembers'));
    }

    /**
     * Create a new calendar event
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'type' => 'required|in:meeting,deadline,milestone,personal',
            'project_id' => 'nullable|string',
            'attendees' => 'nullable|array',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        // In a real application, you would save this to the database
        $event = [
            'id' => 'event_' . uniqid(),
            'title' => $validated['title'],
            'start' => $validated['start'],
            'end' => $validated['end'],
            'type' => $validated['type'],
            'project_id' => $validated['project_id'],
            'attendees' => $validated['attendees'] ?? [],
            'description' => $validated['description'],
            'location' => $validated['location'],
            'created_at' => now()->toISOString(),
        ];

        return redirect()->route('app.calendar.index')
            ->with('success', 'Event created successfully');
    }

    /**
     * Update an existing calendar event
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'start' => 'sometimes|date',
            'end' => 'sometimes|date|after:start',
            'type' => 'sometimes|in:meeting,deadline,milestone,personal',
            'project_id' => 'nullable|string',
            'attendees' => 'nullable|array',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        // In a real application, you would update this in the database
        $event = [
            'id' => $id,
            'updated_at' => now()->toISOString(),
        ];

        return redirect()->route('app.calendar.index')
            ->with('success', 'Event updated successfully');
    }

    /**
     * Delete a calendar event
     */
    public function destroy(Request $request, string $id)
    {
        // In a real application, you would delete this from the database
        return redirect()->route('app.calendar.index')
            ->with('success', 'Event deleted successfully');
    }
}
