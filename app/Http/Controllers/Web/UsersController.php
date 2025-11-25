<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\UserManagementService;
use App\Http\Requests\Unified\UserManagementRequest;
use Illuminate\View\View;
use Illuminate\Http\Request;

/**
 * Users Web Controller
 * 
 * Web controller for user management views.
 * Only returns views - no JSON responses.
 * 
 * This replaces the unified UserManagementController for web routes.
 */
class UsersController extends Controller
{
    public function __construct(
        private UserManagementService $userService
    ) {}

    /**
     * Display users list (Web)
     * 
     * @param UserManagementRequest $request
     * @return View
     */
    public function index(UserManagementRequest $request): View
    {
        $filters = $request->only(['search', 'status', 'role', 'is_active']);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = $request->get('per_page', 15);

        $users = $this->userService->getUsers(
            $filters,
            $perPage,
            $sortBy,
            $sortDirection
        );

        $stats = $this->userService->getUserStats();

        return view('app.users.index', compact('users', 'stats', 'filters'));
    }

    /**
     * Show user profile (Web)
     * 
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            abort(404, 'User not found');
        }

        return view('app.users.show', compact('user'));
    }

    /**
     * Show create user form (Web)
     * 
     * @return View
     */
    public function create(): View
    {
        return view('app.users.create');
    }

    /**
     * Show edit user form (Web)
     * 
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            abort(404, 'User not found');
        }

        return view('app.users.edit', compact('user'));
    }
}

