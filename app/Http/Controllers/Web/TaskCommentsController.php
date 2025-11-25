<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Task Comments Web Controller
 * 
 * Web controller for task comment views.
 * Only returns views - no JSON responses.
 * 
 * This replaces the unified TaskCommentManagementController for web routes.
 */
class TaskCommentsController extends Controller
{
    /**
     * Display comments for a task (Web)
     * 
     * @param string $taskId
     * @return View
     */
    public function index(string $taskId): View
    {
        return view('app.task-comments.index', compact('taskId'));
    }

    /**
     * Show comment (Web)
     * 
     * @param string $id
     * @return View
     */
    public function show(string $id): View
    {
        return view('app.task-comments.show', compact('id'));
    }
}

