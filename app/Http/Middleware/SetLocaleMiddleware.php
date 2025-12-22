<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get supported locales from config
        $supportedLocales = config('app.locales', ['en']);
        $defaultLocale = config('app.locale', 'en');
        
        // Priority: User DB preference > Session > Cookie > Default
        $locale = null;
        
        // Ưu tiên load từ database nếu user đã đăng nhập
        if (Auth::check()) {
            $user = Auth::user();
            if (!empty($user->language)) {
                $locale = $user->language;
            }
        }
        
        // Fallback to session, cookie, or default
        $locale = $locale 
            ?? Session::get('locale') 
            ?? $request->cookie('locale') 
            ?? $defaultLocale;
        
        // Validate locale is supported
        if (!in_array($locale, $supportedLocales, true)) {
            $locale = $defaultLocale;
        }
        
        // Set application locale
        App::setLocale($locale);
        
        // Set Carbon locale for date/time formatting
        try {
            Carbon::setLocale($locale);
        } catch (\Exception $e) {
            // If locale is not available in Carbon, fallback to default
            Carbon::setLocale($defaultLocale);
        }
        
        return $next($request);
    }
}

