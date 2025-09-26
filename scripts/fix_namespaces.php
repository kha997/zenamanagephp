<?php

/**
 * Script sửa namespace sai từ việc di chuyển file
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 Sửa namespace sai...\n";

// Danh sách file cần sửa namespace
$filesToFix = [
    // Middleware
    'app/Http/Middleware/AdminOnlyMiddleware.php' => 'App\Http\Middleware',
    'app/Http/Middleware/ProjectStatusMiddleware.php' => 'App\Http\Middleware',
    'app/Http/Middleware/ComponentAccessMiddleware.php' => 'App\Http\Middleware',
    'app/Http/Middleware/ProjectContextMiddleware.php' => 'App\Http\Middleware',
    'app/Http/Middleware/ProjectOwnershipMiddleware.php' => 'App\Http\Middleware',
    'app/Http/Middleware/ProjectAccessMiddleware.php' => 'App\Http\Middleware',
    'app/Http/Middleware/PermissionMiddleware.php' => 'App\Http\Middleware',
    'app/Http/Middleware/RBACMiddleware.php' => 'App\Http\Middleware',
    'app/Http/Middleware/TaskAccessMiddleware.php' => 'App\Http\Middleware',
    
    // Requests
    'app/Http/Requests/ApplyTemplateRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/StoreProjectRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/ApplyContractRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/RebaselineRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/StoreTaskAssignmentRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateNotificationRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateChangeRequestRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/StoreDocumentRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/SyncTaskAssignmentsRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/StoreNotificationRuleRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/CreateTemplateRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateTaskCompensationRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/StoreComponentRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateDocumentRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateTaskRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/StoreNotificationRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateProjectRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateComponentRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/SubmitChangeRequestRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/CreateBaselineFromProjectRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateTaskAssignmentRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/StoreChangeRequestRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateWorkTemplateRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateBaselineRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateTemplateRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/PreviewCompensationRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/DecideChangeRequestRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/StoreBaselineRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/StoreWorkTemplateRequest.php' => 'App\Http\Requests',
    'app/Http/Requests/UpdateNotificationRuleRequest.php' => 'App\Http\Requests',
    
    // Controllers
    'app/Http/Controllers/PermissionController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/FileController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/RBACController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/RoleController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/AssignmentController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/ProjectTaskController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/InteractionLogController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/CompensationController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/DocumentController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/TemplateController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/WorkTemplateController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/NotificationRuleController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/BaselineController.php' => 'App\Http\Controllers',
    'app/Http/Controllers/PermissionMatrixController.php' => 'App\Http\Controllers',
    
    // Services
    'app/Services/ChangeRequestService.php' => 'App\Services',
    'app/Services/EventBus.php' => 'App\Services',
    'app/Services/ProjectTaskService.php' => 'App\Services',
    'app/Services/FileStorageService.php' => 'App\Services',
    'app/Services/WebSocketService.php' => 'App\Services',
    'app/Services/PermissionMatrixService.php' => 'App\Services',
    'app/Services/RBACManager.php' => 'App\Services',
    'app/Services/WorkTemplateApplicationService.php' => 'App\Services',
    'app/Services/DocumentService.php' => 'App\Services',
    'app/Services/EnhancedMimeValidationService.php' => 'App\Services',
    'app/Services/ValidationService.php' => 'App\Services',
    'app/Services/BaselineService.php' => 'App\Services',
    'app/Services/InteractionLogQueryService.php' => 'App\Services',
    'app/Services/TemplateService.php' => 'App\Services',
    'app/Services/CompensationService.php' => 'App\Services',
    'app/Services/InteractionLogService.php' => 'App\Services',
    'app/Services/ConditionalTagService.php' => 'App\Services',
];

$fixedCount = 0;
$errorCount = 0;

foreach ($filesToFix as $filePath => $correctNamespace) {
    $fullPath = $basePath . '/' . $filePath;
    
    if (!file_exists($fullPath)) {
        echo "  ⚠️ File không tồn tại: {$filePath}\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    
    // Sửa namespace sai
    $content = preg_replace('/^namespace App\\\\Models;$/m', "namespace {$correctNamespace};", $content);
    
    // Sửa use statements sai
    $content = preg_replace('/use App\\\\Models\\\$1;/', '', $content);
    
    if (file_put_contents($fullPath, $content)) {
        echo "  ✅ Fixed: {$filePath}\n";
        $fixedCount++;
    } else {
        echo "  ❌ Failed: {$filePath}\n";
        $errorCount++;
    }
}

echo "\n📊 Kết quả:\n";
echo "  ✅ Fixed: {$fixedCount} files\n";
echo "  ❌ Errors: {$errorCount} files\n";

echo "\n🎯 Hoàn thành sửa namespace!\n";
