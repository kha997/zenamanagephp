<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-129 U-1 — a signed-in web user whose account was deactivated is signed out on the next request, instead of keeping
 * access until the session expires.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $guard->user();

        if ($user instanceof User && !$user->is_active && $guard instanceof StatefulGuard) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Account is deactivated'], 401)
                : redirect('/login')->withErrors(['email' => 'Tài khoản đã bị khoá.']);
        }

        return $next($request);
    }
}
