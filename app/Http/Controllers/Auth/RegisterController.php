<?php declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Register Controller (Render-only)
 * 
 * Renders registration views only.
 * All business logic is handled via API endpoints.
 */
class RegisterController extends Controller
{
    /**
     * Show registration form (render-only)
     */
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }
}
