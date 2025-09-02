<?php declare(strict_types=1);

namespace App\Providers;

use App\Auth\JwtGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request; // Đã có import này
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

        // Đăng ký JWT Guard với Request::capture()
        Auth::extend('jwt', function ($app, $name, array $config) {
            return new JwtGuard(
                Auth::createUserProvider($config['provider']), // Tạo user provider từ config
                Request::capture(), // Sử dụng Request::capture() thay vì inject
                $app->make(AuthService::class) // Inject AuthService để xử lý JWT
            );
        });
    }
}
