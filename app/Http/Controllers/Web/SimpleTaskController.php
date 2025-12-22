<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Simple Task Controller for testing
 */
class SimpleTaskController extends Controller
{
    /**
     * Show the form for creating a new task
     */
    public function create(): View
    {
        // Get projects directly from database
        $projects = Project::where('tenant_id', Auth::user()->tenant_id)
                          ->where('status', '!=', 'cancelled')
                          ->orderBy('name')
                          ->get();

        // Get users from tenant
        $users = User::where('tenant_id', Auth::user()->tenant_id)
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get();

        return view('app.tasks.create-simple', [
            'projects' => $projects,
            'users' => $users
        ]);
    }

    /**
     * Store a newly created task
     */
    public function store(Request $request)
    {
        // Simple validation
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'assignee_id' => 'nullable|exists:users,id',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'end_date' => 'nullable|date|after:today'
        ]);

        // Create task (simplified)
        $task = new \App\Models\Task();
        $task->name = $request->name;
        $task->description = $request->description;
        $task->project_id = $request->project_id;
        $task->assignee_id = $request->assignee_id;
        $task->priority = $request->priority;
        $task->status = $request->status;
        $task->end_date = $request->end_date;
        $task->tenant_id = Auth::user()->tenant_id;
        $task->save();

        return redirect()->route('app.tasks.index')
                        ->with('success', 'Task created successfully!');
    }
}
