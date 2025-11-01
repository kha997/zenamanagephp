<?php declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Password Reset Controller (Render-only)
 * 
 * Renders password reset views only.
 * All business logic is handled via API endpoints.
 */
class PasswordResetController extends Controller
{
    /**
     * Show password reset request form (render-only)
     */
    public function showLinkRequestForm(): View
    {
        return view('auth.passwords.email');
    }

    /**
     * Show password reset form (render-only)
     */
    public function showResetForm(string $token): View
    {
        return view('auth.passwords.reset', [
            'token' => $token
        ]);
    }
}