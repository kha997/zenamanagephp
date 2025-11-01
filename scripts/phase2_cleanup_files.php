<?php

/**
 * PHASE 2: Script cleanup file rác/trùng
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🧹 PHASE 2: CLEANUP FILE RÁC/TRÙNG\n";
echo "==================================\n\n";

// Danh sách file cần xóa (từ phân tích trước)
$filesToDelete = [
    // File test cũ (giữ lại tests/ directory)
    'test_simple_user_api.php',
    'direct_update_test.html',
    'run_all_must_have_tests.php',
    'test_rbac_roles.php',
    'test-status-update.php',
    'test_api_jwt_endpoints.php',
    'test_realtime_sync.php',
    'test_comprehensive_api.php',
    'test-task-edit.html',
    'test_safety_incident.php',
    'test_user_api.php',
    'configure_php_path_and_test.php',
    'test_auth_middleware.php',
    'test_form_submission_browser_final.php',
    'test_inspection_ncr.php',
    'test_single_routes.php',
    'test_submittal_approval.php',
    'test_web_interface.php',
    'test_api_validation.php',
    'test_audit_trail.php',
    'test_ui_navigation.php',
    'test_rfi_workflow.php',
    'test_workflow_engine.php',
    'test_all_api_endpoints.php',
    'test_working_endpoints.php',
    'test_laravel_boot.php',
    'test_e2e_integration.php',
    'test_protected_routes.php',
    'test_document_functionality.php',
    'test_debug_form.html',
    'generate_test_coverage_report.php',
    'test_real_form_submission.php',
    'test_all_modules.php',
    'test_file_upload_debug.php',
    'test_kpi_dashboard.php',
    'test_rate_limiting.php',
    'test_project_document_connection.php',
    'test_integration_testing.php',
    'run_tests.php',
    'test_task_dependencies.php',
    'test_all_project_functions.php',
    'simple_api_test.php',
    'complete_update_test.html',
    'test_user_registration.php',
    'test-task-edit-automated.php',
    'test_e2e_simple.php',
    'test_basic_api.php',
    'test_site_diary.php',
    'test_multi_level_approval.php',
    'test_form_javascript.php',
    'test_form_comparison.php',
    'quick_test.php',
    'test_complete_upload_workflow.php',
    'test_document_versioning.php',
    'simple-test.php',
    'test_dashboard_buttons.php',
    'test_project_api.php',
    'test_user_routes.php',
    'test_secure_upload.php',
    'test_api_endpoints.php',
    'test_jwt_auth.php',
    'test_user_management.php',
    'test_mobile_optimization.php',
    'test_rbac_step_by_step.php',
    'test_baseline_management.php',
    'test_project_creation.php',
    'test_visibility_control.php',
    'test-browser-automated.php',
    'run_comprehensive_tests.php',
    'test_user_simple.php',
    'test_multi_tenant.php',
    'test_must_have_features.php',
    'test_document_fix.php',
    'test_simple_upload_form.html',
    'test_change_request.php',
    'test_form_upload.php',
    'test_web_routes.php',
    'test_after_auth_fix.php',
    
    // File debug
    'debug-frontend.html',
    'debug_jwt_auth.php',
    'debug_api_direct.php',
    'debug_api_error.php',
    'debug_update_button.php',
    'debug_http_500_detailed.php',
    'debug_real_update.html',
    'debug_storage_permissions.php',
    'debug_config_binding.php',
    'debug-network.html',
    
    // File backup
    'composer.json.backup.20250901_090354',
    'app/Providers/AuthServiceProvider.php.backup.2025-09-03-08-41-54',
    'app/Providers/AuthServiceProvider.php.backup.2025-09-03-08-09-43',
    'app/Http/Kernel.php.bak',
    'config/domain.php.bak',
    'config/apache-virtual-host.conf.bak',
    'config/app.php.backup.1756709163',
    'config/nginx-virtual-host.conf.bak',
    'config/app.php.backup',
    'resources/views/tasks/create.blade.php.backup',
    '.env.backup',
    '.env.bak',
    'scripts/deploy.sh.bak',
    'scripts/start-production-workers.sh.bak',
    'scripts/production-monitoring-dashboard.sh.bak',
    'scripts/setup-monitoring-alerts.sh.bak',
    'scripts/test-smtp-production.sh.bak',
    'scripts/run-tests.sh.bak',
    'scripts/backup-database.sh.bak',
    'scripts/deploy-production.sh.bak',
    'scripts/monitor-system.sh.bak',
    'scripts/configure-production-smtp.sh.bak',
    'scripts/backup-files.sh.bak',
    'scripts/setup-supervisor.sh.bak',
    'scripts/monitor.sh.bak',
    'scripts/cleanup-logs.sh.bak',
    'scripts/performance-monitor.sh.bak',
    'scripts/setup-cron.sh.bak',
    'scripts/health-check.sh.bak',
    'scripts/maintenance-database.sh.bak',
    'scripts/start-workers.sh.bak',
    'scripts/run-comprehensive-tests.sh.bak',
    'scripts/start-websocket-server.sh.bak',
    'scripts/manage-workers.sh.bak',
    'scripts/test-production-email-flow.sh.bak',
    'scripts/setup-production.sh.bak',
    'scripts/setup-development.sh.bak',
    'scripts/setup-production-monitoring.sh.bak',
    'scripts/fix-redis-compatibility.sh.bak',
    'scripts/update-cron.sh.bak',
    '.env.backup.demo.20250918_150807',
    'index.php.backup.disabled',
    '.env.backup.domain.20250918_150822',
    'composer.lock.backup.20250901_090354',
    'routes/api.php.backup_2025-09-01_13-20-28',
    'routes/api.php.backup.2025-09-01_06-33-39',
    'routes/api.php.backup_2025-09-01_08-22-25',
    'routes/api.php.backup_2025-09-01_08-26-22',
    'routes/api.php.backup_2025-09-01_08-23-23',
    'routes/api.php.backup_2025-09-01_08-24-35',
    'routes/api.php.backup_2025-09-01_06-30-47',
    'routes/api.php.backup_2025-09-01_13-18-28',
    'routes/api.php.backup',
    'routes/api.php.backup_2025-09-01_13-22-29',
    'routes/api.php.backup_2025-09-01_13-23-29',
    '.env.backup.20250918_145334',
    
    // File log ngoài storage/logs
    'structure_standardization.log',
    'websocket.log',
    'vite.log',
    
    // File HTML standalone
    'test-task-edit.html',
    'test_debug_form.html',
    'complete_update_test.html',
    'debug_real_update.html',
    'test_simple_upload_form.html',
    'debug-network.html',
    
    // File public test/debug
    'public/direct_update_test.html',
    'public/working_update_test.html',
    'public/simple_update_test.html',
    'public/test-upload.html',
    'public/simple_button_test.html',
    'public/user-management-test.html',
    'public/test-smart-search.html',
    'public/websocket_test.html',
    'public/debug-upload.html',
    'public/debug_update_button.html',
    
    // File routes test
    'routes/test.php',
];

// Thư mục cần xóa
$dirsToDelete = [
    'frontend/node_modules/.vite-temp',
    'frontend/node_modules/@mswjs',
    'frontend/node_modules/@open-draft',
    'frontend/node_modules/@bundled-es-modules',
    'frontend/node_modules/@inquirer',
    'frontend/src/pages/admin',
    'frontend/src/pages/change-requests',
    'tests/Browser/Components',
    'docs/testing',
    'docs/guides',
    'docs/deployment',
    'docs/api',
    'docs/reports',
    'storage/app/exports',
    'storage/framework/cache/data',
    'storage/framework/testing/disks/local',
    '.git/refs/tags',
    '.git/refs/remotes/origin',
    'backup/cleanup-20250917-064116',
];

$deletedFiles = 0;
$deletedDirs = 0;
$totalSize = 0;
$errors = 0;

echo "🗑️ Bắt đầu xóa file...\n\n";

// Xóa file
foreach ($filesToDelete as $file) {
    $fullPath = $basePath . '/' . $file;
    
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        if (unlink($fullPath)) {
            echo "  ✅ Deleted: {$file} (" . formatBytes($size) . ")\n";
            $deletedFiles++;
            $totalSize += $size;
        } else {
            echo "  ❌ Failed: {$file}\n";
            $errors++;
        }
    } else {
        echo "  ⚠️ Not found: {$file}\n";
    }
}

echo "\n🗂️ Bắt đầu xóa thư mục...\n\n";

// Xóa thư mục
foreach ($dirsToDelete as $dir) {
    $fullPath = $basePath . '/' . $dir;
    
    if (is_dir($fullPath)) {
        if (rmdir($fullPath)) {
            echo "  ✅ Deleted dir: {$dir}\n";
            $deletedDirs++;
        } else {
            echo "  ❌ Failed dir: {$dir}\n";
            $errors++;
        }
    } else {
        echo "  ⚠️ Dir not found: {$dir}\n";
    }
}

echo "\n📊 KẾT QUẢ CLEANUP:\n";
echo "==================\n";
echo "  ✅ Files deleted: {$deletedFiles}\n";
echo "  ✅ Directories deleted: {$deletedDirs}\n";
echo "  💾 Space freed: " . formatBytes($totalSize) . "\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành cleanup PHASE 2!\n";

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
