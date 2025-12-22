<?php declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Login Controller (Render-only)
 * 
 * Renders login views and handles UI interactions.
 * All business logic is delegated to API endpoints.
 */
class LoginController extends Controller
{
    /**
     * Show login form (render-only)
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        try {
            $user = Auth::user();
            
            if ($user) {
                // Log logout
                Log::info('User logged out via web', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                    'request_id' => $request->header('X-Request-Id')
                ]);
            }

            // Clear session
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')
                ->with('success', 'Logged out successfully!');

        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'ip' => $request->ip()
            ]);

            // Still logout locally even if API call fails
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')
                ->with('success', 'Logged out successfully!');
        }
    }
}
