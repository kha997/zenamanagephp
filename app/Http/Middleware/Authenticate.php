<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // API requests should let Laravel return JSON 401 responses.
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Web requests should redirect to the login page.
        return '/login';
    }
}
