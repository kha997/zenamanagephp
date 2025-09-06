<?php declare(strict_types=1);

/**
 * Script sửa lỗi AuthServiceProvider
 * Khắc phục lỗi "Object of type Illuminate\Auth\AuthManager is not callable"
 * 
 * Nguyên nhân: Request::capture() trong AuthServiceProvider gây xung đột
 * Giải pháp: Sử dụng $app['request'] thay vì Request::capture()
 */

echo "🔧 Đang sửa lỗi AuthServiceProvider...\n";

$authServiceProviderPath = __DIR__ . '/app/Providers/AuthServiceProvider.php';

if (!file_exists($authServiceProviderPath)) {
    echo "❌ Không tìm thấy file AuthServiceProvider.php\n";
    exit(1);
}

// Backup file gốc
$backupPath = $authServiceProviderPath . '.backup.' . date('Y-m-d-H-i-s');
copy($authServiceProviderPath, $backupPath);
echo "📋 Đã backup file gốc: $backupPath\n";

// Nội dung file mới đã sửa
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

        // ✅ SỬA: Inject Request đúng cách từ container
        Auth::extend('jwt', function ($app, $name, array $config) {
            return new JwtGuard(
                Auth::createUserProvider($config['provider']),
                $app['request'], // ✅ Sử dụng $app['request'] thay vì Request::capture()
                $app->make(AuthService::class)
            );
        });
    }
}
PHP;

// Ghi file mới
if (file_put_contents($authServiceProviderPath, $newContent) !== false) {
    echo "✅ Đã sửa AuthServiceProvider thành công!\n";
    echo "\n🔍 Các thay đổi chính:\n";
    echo "   - Thay Request::capture() bằng \$app['request']\n";
    echo "   - Loại bỏ import Illuminate\Http\Request không cần thiết\n";
    echo "   - Cải thiện cách inject dependencies\n";
    
    echo "\n🧪 Bây giờ bạn có thể test lại API với lệnh:\n";
    echo "curl -X GET \"http://localhost:8000/api/v1/auth/me\" \\\n";
    echo "  -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n";
    echo "  -H \"Content-Type: application/json\"\n";
    
} else {
    echo "❌ Không thể ghi file AuthServiceProvider\n";
    // Khôi phục file backup
    copy($backupPath, $authServiceProviderPath);
    echo "🔄 Đã khôi phục file gốc\n";
    exit(1);
}

echo "\n✨ Hoàn thành sửa lỗi!\n";
?>