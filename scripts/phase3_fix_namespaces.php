<?php

/**
 * PHASE 3: Script sửa namespace PSR-4 không đúng
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 PHASE 3: SỬA NAMESPACE PSR-4 KHÔNG ĐÚNG\n";
echo "========================================\n\n";

$fixedFiles = 0;
$errors = 0;

// Danh sách file cần sửa namespace
$filesToFix = [
    // Requests
    'app/Http/Requests/Auth/RegisterRequest.php' => 'App\Http\Requests\Auth\RegisterRequest',
    'app/Http/Requests/Auth/LoginRequest.php' => 'App\Http\Requests\Auth\LoginRequest',
    'app/Http/Requests/User/StoreUserRequest.php' => 'App\Http\Requests\User\StoreUserRequest',
    
    // Controllers Web
    'app/Http/Controllers/Web/TaskController.php' => 'App\Http\Controllers\Web\TaskController',
    'app/Http/Controllers/Web/AnalyticsController.php' => 'App\Http\Controllers\Web\AnalyticsController',
    'app/Http/Controllers/Web/AlertController.php' => 'App\Http\Controllers\Web\AlertController',
    'app/Http/Controllers/Web/DocumentManagementController.php' => 'App\Http\Controllers\Web\DocumentManagementController',
    'app/Http/Controllers/Web/DocumentController.php' => 'App\Http\Controllers\Web\DocumentController',
    'app/Http/Controllers/Web/ProjectBulkController.php' => 'App\Http\Controllers\Web\ProjectBulkController',
    'app/Http/Controllers/Web/TaskBulkController.php' => 'App\Http\Controllers\Web\TaskBulkController',
    
    // Controllers Admin
    'app/Http/Controllers/Admin/MaintenanceController.php' => 'App\Http\Controllers\Admin\MaintenanceController',
    'app/Http/Controllers/Admin/SidebarBuilderController.php' => 'App\Http\Controllers\Admin\SidebarBuilderController',
    'app/Http/Controllers/Admin/SimpleSidebarBuilderController.php' => 'App\Http\Controllers\Admin\SimpleSidebarBuilderController',
    'app/Http/Controllers/Admin/BasicSidebarController.php' => 'App\Http\Controllers\Admin\BasicSidebarController',
    
    // Controllers API
    'app/Http/Controllers/Api/SecurityDashboardController.php' => 'App\Http\Controllers\Api\SecurityDashboardController',
    'app/Http/Controllers/Api/ProjectTemplateController.php' => 'App\Http\Controllers\Api\ProjectTemplateController',
    'app/Http/Controllers/Api/NotificationController.php' => 'App\Http\Controllers\Api\NotificationController',
    'app/Http/Controllers/Api/ChangeRequestController.php' => 'App\Http\Controllers\Api\ChangeRequestController',
    'app/Http/Controllers/Api/ProjectAnalyticsController.php' => 'App\Http\Controllers\Api\ProjectAnalyticsController',
    'app/Http/Controllers/Api/ProjectController.php' => 'App\Http\Controllers\Api\ProjectController',
    'app/Http/Controllers/Api/CalendarIntegrationController.php' => 'App\Http\Controllers\Api\CalendarIntegrationController',
    'app/Http/Controllers/Api/ThirdPartyController.php' => 'App\Http\Controllers\Api\ThirdPartyController',
    'app/Http/Controllers/Api/BaseApiController.php' => 'App\Http\Controllers\Api\BaseApiController',
    'app/Http/Controllers/Api/ExportController.php' => 'App\Http\Controllers\Api\ExportController',
    'app/Http/Controllers/Api/DrawingController.php' => 'App\Http\Controllers\Api\DrawingController',
    'app/Http/Controllers/Api/SidebarConfigController.php' => 'App\Http\Controllers\Api\SidebarConfigController',
    'app/Http/Controllers/Api/DashboardController.php' => 'App\Http\Controllers\Api\DashboardController',
    'app/Http/Controllers/Api/ComponentController.php' => 'App\Http\Controllers\Api\ComponentController',
    'app/Http/Controllers/Api/PerformanceController.php' => 'App\Http\Controllers\Api\PerformanceController',
    'app/Http/Controllers/Api/EmailVerificationController.php' => 'App\Http\Controllers\Api\EmailVerificationController',
    'app/Http/Controllers/Api/TaskController.php' => 'App\Http\Controllers\Api\TaskController',
    'app/Http/Controllers/Api/AuthController.php' => 'App\Http\Controllers\Api\AuthController',
    'app/Http/Controllers/Api/ProjectManagerDashboardController.php' => 'App\Http\Controllers\Api\ProjectManagerDashboardController',
    'app/Http/Controllers/Api/Admin/TenantDashboardController.php' => 'App\Http\Controllers\Api\Admin\TenantDashboardController',
    'app/Http/Controllers/Api/AnalyticsController.php' => 'App\Http\Controllers\Api\AnalyticsController',
    'app/Http/Controllers/Api/SimpleDocumentController.php' => 'App\Http\Controllers\Api\SimpleDocumentController',
    'app/Http/Controllers/Api/DesignerDashboardController.php' => 'App\Http\Controllers\Api\DesignerDashboardController',
    'app/Http/Controllers/Api/CloudStorageController.php' => 'App\Http\Controllers\Api\CloudStorageController',
    'app/Http/Controllers/Api/ZenaDashboardController.php' => 'App\Http\Controllers\Api\ZenaDashboardController',
    'app/Http/Controllers/Api/InspectionController.php' => 'App\Http\Controllers\Api\InspectionController',
    'app/Http/Controllers/Api/SubmittalController.php' => 'App\Http\Controllers\Api\SubmittalController',
    'app/Http/Controllers/Api/UserPreferenceController.php' => 'App\Http\Controllers\Api\UserPreferenceController',
    'app/Http/Controllers/Api/TaskAssignmentController.php' => 'App\Http\Controllers\Api\TaskAssignmentController',
    'app/Http/Controllers/Api/ProjectMilestoneController.php' => 'App\Http\Controllers\Api\ProjectMilestoneController',
    'app/Http/Controllers/Api/RealTimeController.php' => 'App\Http\Controllers\Api\RealTimeController',
    'app/Http/Controllers/Api/PmDashboardController.php' => 'App\Http\Controllers\Api\PmDashboardController',
    'app/Http/Controllers/Api/DocumentController.php' => 'App\Http\Controllers\Api\DocumentController',
    'app/Http/Controllers/Api/BulkOperationsController.php' => 'App\Http\Controllers\Api\BulkOperationsController',
    'app/Http/Controllers/Api/TeamController.php' => 'App\Http\Controllers\Api\TeamController',
    'app/Http/Controllers/Api/DashboardCustomizationController.php' => 'App\Http\Controllers\Api\DashboardCustomizationController',
    'app/Http/Controllers/Api/SiteEngineerDashboardController.php' => 'App\Http\Controllers\Api\SiteEngineerDashboardController',
    'app/Http/Controllers/Api/DashboardSSEController.php' => 'App\Http\Controllers\Api\DashboardSSEController',
    'app/Http/Controllers/Api/BadgeController.php' => 'App\Http\Controllers\Api\BadgeController',
    'app/Http/Controllers/Api/DashboardRoleBasedController.php' => 'App\Http\Controllers\Api\DashboardRoleBasedController',
    'app/Http/Controllers/Api/RfiController.php' => 'App\Http\Controllers\Api\RfiController',
];

echo "🔧 Bắt đầu sửa namespace...\n\n";

foreach ($filesToFix as $filePath => $correctNamespace) {
    $fullPath = $basePath . '/' . $filePath;
    
    if (!file_exists($fullPath)) {
        echo "  ⚠️ Not found: {$filePath}\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $originalContent = $content;
    
    // Sửa namespace
    $content = preg_replace('/^namespace\s+[^;]+;/m', "namespace {$correctNamespace};", $content);
    
    if ($content !== $originalContent) {
        if (file_put_contents($fullPath, $content)) {
            echo "  ✅ Fixed: {$filePath}\n";
            $fixedFiles++;
        } else {
            echo "  ❌ Failed: {$filePath}\n";
            $errors++;
        }
    } else {
        echo "  ⚠️ No change needed: {$filePath}\n";
    }
}

echo "\n📊 KẾT QUẢ SỬA NAMESPACE:\n";
echo "========================\n";
echo "  ✅ Files fixed: {$fixedFiles}\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành sửa namespace PHASE 3!\n";
