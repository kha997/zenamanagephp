<?php

/**
 * Script khôi phục file từ src về app
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔄 Khôi phục file từ src...\n";

// Mapping từ src sang app
$restoreMappings = [
    // Controllers
    'src/CoreProject/Controllers/TaskController.php' => 'app/Http/Controllers/TaskController.php',
    'src/CoreProject/Controllers/ProjectController.php' => 'app/Http/Controllers/ProjectController.php',
    'src/CoreProject/Controllers/BaselineController.php' => 'app/Http/Controllers/BaselineController.php',
    'src/CoreProject/Controllers/ComponentController.php' => 'app/Http/Controllers/ComponentController.php',
    'src/CoreProject/Controllers/TaskAssignmentController.php' => 'app/Http/Controllers/TaskAssignmentController.php',
    'src/CoreProject/Controllers/WorkTemplateController.php' => 'app/Http/Controllers/WorkTemplateController.php',
    
    'src/ChangeRequest/Controllers/ChangeRequestController.php' => 'app/Http/Controllers/ChangeRequestController.php',
    'src/Compensation/Controllers/CompensationController.php' => 'app/Http/Controllers/CompensationController.php',
    'src/DocumentManagement/Controllers/DocumentController.php' => 'app/Http/Controllers/DocumentController.php',
    'src/InteractionLogs/Controllers/InteractionLogController.php' => 'app/Http/Controllers/InteractionLogController.php',
    'src/Notification/Controllers/NotificationController.php' => 'app/Http/Controllers/NotificationController.php',
    'src/Notification/Controllers/NotificationRuleController.php' => 'app/Http/Controllers/NotificationRuleController.php',
    'src/RBAC/Controllers/PermissionController.php' => 'app/Http/Controllers/PermissionController.php',
    'src/RBAC/Controllers/RBACController.php' => 'app/Http/Controllers/RBACController.php',
    'src/RBAC/Controllers/RoleController.php' => 'app/Http/Controllers/RoleController.php',
    'src/RBAC/Controllers/AssignmentController.php' => 'app/Http/Controllers/AssignmentController.php',
    'src/RBAC/Controllers/PermissionMatrixController.php' => 'app/Http/Controllers/PermissionMatrixController.php',
    'src/WorkTemplate/Controllers/TemplateController.php' => 'app/Http/Controllers/TemplateController.php',
    'src/WorkTemplate/Controllers/WorkTemplateController.php' => 'app/Http/Controllers/WorkTemplateController.php',
    
    // Services
    'src/CoreProject/Services/TaskService.php' => 'app/Services/TaskService.php',
    'src/CoreProject/Services/ProjectService.php' => 'app/Services/ProjectService.php',
    'src/CoreProject/Services/BaselineService.php' => 'app/Services/BaselineService.php',
    'src/CoreProject/Services/ComponentService.php' => 'app/Services/ComponentService.php',
    'src/CoreProject/Services/TaskAssignmentService.php' => 'app/Services/TaskAssignmentService.php',
    'src/CoreProject/Services/WorkTemplateService.php' => 'app/Services/WorkTemplateService.php',
    'src/CoreProject/Services/ConditionalTagService.php' => 'app/Services/ConditionalTagService.php',
    
    'src/ChangeRequest/Services/ChangeRequestService.php' => 'app/Services/ChangeRequestService.php',
    'src/Compensation/Services/CompensationService.php' => 'app/Services/CompensationService.php',
    'src/DocumentManagement/Services/DocumentService.php' => 'app/Services/DocumentService.php',
    'src/Foundation/Services/EventBus.php' => 'app/Services/EventBus.php',
    'src/Foundation/Services/FileStorageService.php' => 'app/Services/FileStorageService.php',
    'src/Foundation/Services/WebSocketService.php' => 'app/Services/WebSocketService.php',
    'src/Foundation/Services/ValidationService.php' => 'app/Services/ValidationService.php',
    'src/Foundation/Services/EnhancedMimeValidationService.php' => 'app/Services/EnhancedMimeValidationService.php',
    'src/InteractionLogs/Services/InteractionLogService.php' => 'app/Services/InteractionLogService.php',
    'src/InteractionLogs/Services/InteractionLogQueryService.php' => 'app/Services/InteractionLogQueryService.php',
    'src/Notification/Services/NotificationService.php' => 'app/Services/NotificationService.php',
    'src/Notification/Services/NotificationRuleService.php' => 'app/Services/NotificationRuleService.php',
    'src/RBAC/Services/RBACManager.php' => 'app/Services/RBACManager.php',
    'src/RBAC/Services/PermissionMatrixService.php' => 'app/Services/PermissionMatrixService.php',
    'src/WorkTemplate/Services/TemplateService.php' => 'app/Services/TemplateService.php',
    'src/WorkTemplate/Services/WorkTemplateApplicationService.php' => 'app/Services/WorkTemplateApplicationService.php',
    
    // Models
    'src/CoreProject/Models/Task.php' => 'app/Models/Task.php',
    'src/CoreProject/Models/Project.php' => 'app/Models/Project.php',
    'src/CoreProject/Models/Baseline.php' => 'app/Models/Baseline.php',
    'src/CoreProject/Models/BaselineHistory.php' => 'app/Models/BaselineHistory.php',
    'src/CoreProject/Models/Component.php' => 'app/Models/Component.php',
    'src/CoreProject/Models/ComponentKpi.php' => 'app/Models/ComponentKpi.php',
    'src/CoreProject/Models/ProjectTask.php' => 'app/Models/ProjectTask.php',
    'src/CoreProject/Models/ProjectPhase.php' => 'app/Models/ProjectPhase.php',
    
    'src/ChangeRequest/Models/ChangeRequest.php' => 'app/Models/ChangeRequest.php',
    'src/ChangeRequest/Models/CrLink.php' => 'app/Models/CrLink.php',
    'src/Compensation/Models/Contract.php' => 'app/Models/Contract.php',
    'src/Compensation/Models/TaskCompensation.php' => 'app/Models/TaskCompensation.php',
    'src/DocumentManagement/Models/Document.php' => 'app/Models/Document.php',
    'src/DocumentManagement/Models/DocumentVersion.php' => 'app/Models/DocumentVersion.php',
    'src/InteractionLogs/Models/InteractionLog.php' => 'app/Models/InteractionLog.php',
    'src/Notification/Models/Notification.php' => 'app/Models/Notification.php',
    'src/Notification/Models/NotificationRule.php' => 'app/Models/NotificationRule.php',
    'src/RBAC/Models/Role.php' => 'app/Models/Role.php',
    'src/RBAC/Models/Permission.php' => 'app/Models/Permission.php',
    'src/RBAC/Models/RolePermission.php' => 'app/Models/RolePermission.php',
    'src/RBAC/Models/UserRoleSystem.php' => 'app/Models/UserRoleSystem.php',
    'src/RBAC/Models/UserRoleProject.php' => 'app/Models/UserRoleProject.php',
    'src/RBAC/Models/UserRoleCustom.php' => 'app/Models/UserRoleCustom.php',
    'src/WorkTemplate/Models/Template.php' => 'app/Models/Template.php',
    'src/WorkTemplate/Models/TemplateVersion.php' => 'app/Models/TemplateVersion.php',
    'src/WorkTemplate/Models/WorkTemplate.php' => 'app/Models/WorkTemplate.php',
    
    // Requests
    'src/CoreProject/Requests/StoreProjectRequest.php' => 'app/Http/Requests/StoreProjectRequest.php',
    'src/CoreProject/Requests/UpdateProjectRequest.php' => 'app/Http/Requests/UpdateProjectRequest.php',
    'src/CoreProject/Requests/StoreTaskRequest.php' => 'app/Http/Requests/StoreTaskRequest.php',
    'src/CoreProject/Requests/UpdateTaskRequest.php' => 'app/Http/Requests/UpdateTaskRequest.php',
    'src/CoreProject/Requests/StoreTaskAssignmentRequest.php' => 'app/Http/Requests/StoreTaskAssignmentRequest.php',
    'src/CoreProject/Requests/UpdateTaskAssignmentRequest.php' => 'app/Http/Requests/UpdateTaskAssignmentRequest.php',
    'src/CoreProject/Requests/StoreBaselineRequest.php' => 'app/Http/Requests/StoreBaselineRequest.php',
    'src/CoreProject/Requests/UpdateBaselineRequest.php' => 'app/Http/Requests/UpdateBaselineRequest.php',
    'src/CoreProject/Requests/CreateBaselineFromProjectRequest.php' => 'app/Http/Requests/CreateBaselineFromProjectRequest.php',
    'src/CoreProject/Requests/RebaselineRequest.php' => 'app/Http/Requests/RebaselineRequest.php',
    'src/CoreProject/Requests/StoreComponentRequest.php' => 'app/Http/Requests/StoreComponentRequest.php',
    'src/CoreProject/Requests/UpdateComponentRequest.php' => 'app/Http/Requests/UpdateComponentRequest.php',
    'src/CoreProject/Requests/StoreWorkTemplateRequest.php' => 'app/Http/Requests/StoreWorkTemplateRequest.php',
    'src/CoreProject/Requests/UpdateWorkTemplateRequest.php' => 'app/Http/Requests/UpdateWorkTemplateRequest.php',
    
    'src/ChangeRequest/Requests/StoreChangeRequestRequest.php' => 'app/Http/Requests/StoreChangeRequestRequest.php',
    'src/ChangeRequest/Requests/UpdateChangeRequestRequest.php' => 'app/Http/Requests/UpdateChangeRequestRequest.php',
    'src/ChangeRequest/Requests/SubmitChangeRequestRequest.php' => 'app/Http/Requests/SubmitChangeRequestRequest.php',
    'src/ChangeRequest/Requests/DecideChangeRequestRequest.php' => 'app/Http/Requests/DecideChangeRequestRequest.php',
    'src/Compensation/Requests/ApplyContractRequest.php' => 'app/Http/Requests/ApplyContractRequest.php',
    'src/Compensation/Requests/UpdateTaskCompensationRequest.php' => 'app/Http/Requests/UpdateTaskCompensationRequest.php',
    'src/Compensation/Requests/PreviewCompensationRequest.php' => 'app/Http/Requests/PreviewCompensationRequest.php',
    'src/Compensation/Requests/SyncTaskAssignmentsRequest.php' => 'app/Http/Requests/SyncTaskAssignmentsRequest.php',
    'src/DocumentManagement/Requests/StoreDocumentRequest.php' => 'app/Http/Requests/StoreDocumentRequest.php',
    'src/DocumentManagement/Requests/UpdateDocumentRequest.php' => 'app/Http/Requests/UpdateDocumentRequest.php',
    'src/Notification/Requests/StoreNotificationRequest.php' => 'app/Http/Requests/StoreNotificationRequest.php',
    'src/Notification/Requests/UpdateNotificationRequest.php' => 'app/Http/Requests/UpdateNotificationRequest.php',
    'src/Notification/Requests/StoreNotificationRuleRequest.php' => 'app/Http/Requests/StoreNotificationRuleRequest.php',
    'src/Notification/Requests/UpdateNotificationRuleRequest.php' => 'app/Http/Requests/UpdateNotificationRuleRequest.php',
    'src/WorkTemplate/Requests/CreateTemplateRequest.php' => 'app/Http/Requests/CreateTemplateRequest.php',
    'src/WorkTemplate/Requests/UpdateTemplateRequest.php' => 'app/Http/Requests/UpdateTemplateRequest.php',
    'src/WorkTemplate/Requests/ApplyTemplateRequest.php' => 'app/Http/Requests/ApplyTemplateRequest.php',
    
    // Middleware
    'src/CoreProject/Middleware/ProjectStatusMiddleware.php' => 'app/Http/Middleware/ProjectStatusMiddleware.php',
    'src/CoreProject/Middleware/ProjectContextMiddleware.php' => 'app/Http/Middleware/ProjectContextMiddleware.php',
    'src/CoreProject/Middleware/ProjectOwnershipMiddleware.php' => 'app/Http/Middleware/ProjectOwnershipMiddleware.php',
    'src/CoreProject/Middleware/ProjectAccessMiddleware.php' => 'app/Http/Middleware/ProjectAccessMiddleware.php',
    'src/CoreProject/Middleware/TaskAccessMiddleware.php' => 'app/Http/Middleware/TaskAccessMiddleware.php',
    'src/CoreProject/Middleware/ComponentAccessMiddleware.php' => 'app/Http/Middleware/ComponentAccessMiddleware.php',
    'src/RBAC/Middleware/RBACMiddleware.php' => 'app/Http/Middleware/RBACMiddleware.php',
    'src/RBAC/Middleware/PermissionMiddleware.php' => 'app/Http/Middleware/PermissionMiddleware.php',
    'src/Foundation/Middleware/AdminOnlyMiddleware.php' => 'app/Http/Middleware/AdminOnlyMiddleware.php',
];

$restoredCount = 0;
$errorCount = 0;

foreach ($restoreMappings as $srcPath => $appPath) {
    $fullSrcPath = $basePath . '/' . $srcPath;
    $fullAppPath = $basePath . '/' . $appPath;
    
    if (!file_exists($fullSrcPath)) {
        echo "  ⚠️ Source not found: {$srcPath}\n";
        continue;
    }
    
    // Ensure target directory exists
    $targetDir = dirname($fullAppPath);
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    if (copy($fullSrcPath, $fullAppPath)) {
        echo "  ✅ Restored: {$appPath}\n";
        $restoredCount++;
    } else {
        echo "  ❌ Failed: {$appPath}\n";
        $errorCount++;
    }
}

echo "\n📊 Kết quả:\n";
echo "  ✅ Restored: {$restoredCount} files\n";
echo "  ❌ Errors: {$errorCount} files\n";

echo "\n🎯 Hoàn thành khôi phục!\n";
