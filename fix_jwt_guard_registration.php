<?php declare(strict_types=1);

/**
 * Script sửa lỗi JWT Guard registration trong AuthServiceProvider
 * Lỗi: "Object of type Illuminate\Auth\AuthManager is not callable"
 */

require_once __DIR__ . '/vendor/autoload.php';

$authServiceProviderPath = __DIR__ . '/app/Providers/AuthServiceProvider.php';
$backupPath = $authServiceProviderPath . '.backup.' . date('Y-m-d-H-i-s');

echo "🔧 Sửa lỗi JWT Guard registration...\n";

// Backup file gốc
if (!copy($authServiceProviderPath, $backupPath)) {
    die("❌ Không thể tạo backup file!\n");
}
echo "✅ Đã backup file gốc: $backupPath\n";

// Nội dung AuthServiceProvider mới
$newContent = <<<'PHP'
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
PHP;

// Ghi file mới
if (file_put_contents($authServiceProviderPath, $newContent) === false) {
    die("❌ Không thể ghi file AuthServiceProvider!\n");
}

echo "✅ Đã sửa AuthServiceProvider\n";
echo "🔄 Hãy clear cache và test lại API:\n";
echo "   php artisan config:clear\n";
echo "   php artisan cache:clear\n";
echo "   curl -v -X POST http://localhost/zenamanage/public/api/v1/auth/login -H \"Content-Type: application/json\" -d '{\"email\":\"admin@zena.local\",\"password\":\"password123\"}'\n";