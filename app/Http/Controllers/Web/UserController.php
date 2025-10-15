<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web User Controller
 * 
 * Renders user management views and handles UI interactions.
 * All business logic is delegated to API endpoints.
 */
class UserController extends Controller
{
    /**
     * Display users index page
     */
    public function index(Request $request): View
    {
        // For now, return empty view until AppApiGateway is implemented
        return view('app.users.index', [
            'users' => [],
            'pagination' => [],
            'filters' => [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
            ],
            'message' => 'User management interface coming soon...'
        ]);
    }

    /**
     * Display user detail page
     */
    public function show(Request $request, string $id): View
    {
        // For now, return empty view until AppApiGateway is implemented
        return view('app.users.show', [
            'user' => null,
            'message' => 'User detail interface coming soon...'
        ]);
    }
}
