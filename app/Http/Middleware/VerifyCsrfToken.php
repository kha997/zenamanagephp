<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'test-login-simple',
        'test-api-*',
        'api/*',
        'app/api/*',
        'admin/maintenance',
        'admin/maintenance/*',
    ];

    /**
     * GAP-130 S-1 — a stale token on the login form (double submit, or a login page left open past the session
     * lifetime) recovers instead of showing "419 Page Expired". CSRF stays enforced: the login controller never runs for
     * a request with a bad token, so it cannot sign anyone in. Other routes and JSON requests keep the 419.
     */
    public function handle($request, Closure $next)
    {
        try {
            return parent::handle($request, $next);
        } catch (TokenMismatchException $e) {
            if (!$request->routeIs('login.post') || $request->expectsJson()) {
                throw $e;
            }

            if (Auth::guard('web')->check()) {
                return redirect()->intended('/app/today');
            }

            return redirect('/login')
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.']);
        }
    }

    /**
     * Always enforce CSRF verification during testing so middleware can't be bypassed.
     */
    protected function runningUnitTests()
    {
        return false;
    }
}
