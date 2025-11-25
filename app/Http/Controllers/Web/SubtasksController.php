<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Subtasks Web Controller
 * 
 * Web controller for subtask views.
 * Only returns views - no JSON responses.
 * 
 * This replaces the unified SubtaskManagementController for web routes.
 */
class SubtasksController extends Controller
{
    /**
     * Display subtasks for a task (Web)
     * 
     * @param string $taskId
     * @return View
     */
    public function index(string $taskId): View
    {
        return view('app.subtasks.index', compact('taskId'));
    }

    /**
     * Show subtask (Web)
     * 
     * @param string $id
     * @return View
     */
    public function show(string $id): View
    {
        return view('app.subtasks.show', compact('id'));
    }
}

