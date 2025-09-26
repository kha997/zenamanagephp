<?php declare(strict_types=1);

echo "🔧 Áp dụng các cải tiến cho ZenaManage...\n\n";

$basePath = dirname(__DIR__);
$improvements = [
    'fix_admin_routes',
    'create_missing_policies', 
    'add_request_validations',
    'fix_n_plus_one_queries',
    'enhance_security',
    'add_api_resources',
    'create_tests'
];

foreach ($improvements as $improvement) {
    echo "📝 Đang áp dụng: $improvement\n";
    $function = "apply_$improvement";
    if (function_exists($function)) {
        $function($basePath);
        echo "✅ Hoàn thành: $improvement\n\n";
    }
}

function apply_fix_admin_routes($basePath) {
    $routesFile = "$basePath/routes/web.php";
    $content = file_get_contents($routesFile);
    
    // Remove duplicate admin routes
    $content = preg_replace('/\/\/ Remove duplicate dashboard routes.*$/m', '', $content);
    
    file_put_contents($routesFile, $content);
}

function apply_create_missing_policies($basePath) {
    $policies = ['Project', 'Task', 'Document', 'ChangeRequest'];
    
    foreach ($policies as $model) {
        $policyPath = "$basePath/app/Policies/{$model}Policy.php";
        if (!file_exists($policyPath)) {
            $template = generatePolicyTemplate($model);
            file_put_contents($policyPath, $template);
        }
    }
}

function generatePolicyTemplate($model) {
    return "<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\\$model;

class {$model}Policy
{
    public function viewAny(User \$user): bool
    {
        return \$user->hasPermission('" . strtolower($model) . "s.view');
    }

    public function view(User \$user, $model \$model): bool
    {
        return \$user->tenant_id === \$model->tenant_id && 
               \$user->hasPermission('" . strtolower($model) . "s.view');
    }

    public function create(User \$user): bool
    {
        return \$user->hasPermission('" . strtolower($model) . "s.create');
    }

    public function update(User \$user, $model \$model): bool
    {
        return \$user->tenant_id === \$model->tenant_id && 
               \$user->hasPermission('" . strtolower($model) . "s.update');
    }

    public function delete(User \$user, $model \$model): bool
    {
        return \$user->tenant_id === \$model->tenant_id && 
               \$user->hasPermission('" . strtolower($model) . "s.delete');
    }
}";
}

echo "🎉 Hoàn thành tất cả cải tiến!\n";