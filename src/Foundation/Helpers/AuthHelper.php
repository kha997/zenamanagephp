<?php declare(strict_types=1);

namespace Src\Foundation\Helpers;

use Illuminate\Support\Facades\Auth;
use Src\RBAC\Services\AuthService;
use Illuminate\Http\Request;

class AuthHelper
{
    /**
     * Lấy user ID hiện tại một cách an toàn
     */
    public static function id(): ?string
    {
        try {
            // Thử lấy từ request hiện tại nếu có
            $request = request();
            if ($request && method_exists($request, 'user')) {
                $user = $request->user('api');
                return $user ? (string) $user->id : null;
            }
            
            // Fallback về Auth facade với guard 'api'
            return Auth::guard('api')->id() ? (string) Auth::guard('api')->id() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Kiểm tra user đã đăng nhập chưa
     */
    public static function check(): bool
    {
        try {
            $request = request();
            if ($request && method_exists($request, 'user')) {
                return $request->user('api') !== null;
            }
            
            return Auth::guard('api')->check();
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * Lấy user hiện tại
     */
    public static function user()
    {
        try {
            $request = request();
            if ($request && method_exists($request, 'user')) {
                return $request->user('api');
            }
            
            return Auth::guard('api')->user();
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Lấy ID với fallback về 'system'
     */
    public static function idOrSystem(): string
    {
        return static::id() ?? 'system';
    }
}