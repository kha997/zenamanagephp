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
        // Đối với API requests, không redirect mà trả về null
        // để Laravel tự động trả về JSON response 401 Unauthorized
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }
        
        // Đối với web requests, có thể redirect đến trang login
        // Tuy nhiên, vì chưa có route 'login', tạm thời trả về null
        return null;
    }
}