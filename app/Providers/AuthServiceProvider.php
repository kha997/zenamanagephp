<?php declare(strict_types=1);

namespace App\Providers;

use App\Auth\JwtGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Src\RBAC\Services\AuthService;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // ✅ SỬA: Đăng ký JWT guard với cách tiếp cận khác
        Auth::extend('jwt', function ($app, $name, array $config) {
            // Lấy user provider
            $userProvider = Auth::createUserProvider($config['provider']);
            
            // Tạo JwtGuard instance
            return new JwtGuard(
                $userProvider,
                $app['request'],
                $app->make(AuthService::class)
            );
        });
    }
}