<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Task Attachments Web Controller
 * 
 * Web controller for task attachment views.
 * Only returns views - no JSON responses.
 * 
 * This replaces the unified TaskAttachmentManagementController for web routes.
 */
class TaskAttachmentsController extends Controller
{
    /**
     * Display attachments for a task (Web)
     * 
     * @param string $taskId
     * @return View
     */
    public function index(string $taskId): View
    {
        return view('app.task-attachments.index', compact('taskId'));
    }

    /**
     * Show attachment (Web)
     * 
     * @param string $id
     * @return View
     */
    public function show(string $id): View
    {
        return view('app.task-attachments.show', compact('id'));
    }
}

