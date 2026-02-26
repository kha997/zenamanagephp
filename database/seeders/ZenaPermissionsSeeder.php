<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Seeder;

class ZenaPermissionsSeeder extends Seeder
{
    public const CANONICAL_PERMISSIONS = [
        // RFIs
        ['code' => 'rfi.view', 'module' => 'rfi', 'action' => 'view', 'description' => 'View RFIs from the ZENA API'],
        ['code' => 'rfi.create', 'module' => 'rfi', 'action' => 'create', 'description' => 'Create a new RFI'],
        ['code' => 'rfi.edit', 'module' => 'rfi', 'action' => 'edit', 'description' => 'Edit an existing RFI'],
        ['code' => 'rfi.delete', 'module' => 'rfi', 'action' => 'delete', 'description' => 'Delete an RFI'],
        ['code' => 'rfi.assign', 'module' => 'rfi', 'action' => 'assign', 'description' => 'Assign an RFI to a teammate'],
        ['code' => 'rfi.respond', 'module' => 'rfi', 'action' => 'respond', 'description' => 'Respond to an RFI'],
        ['code' => 'rfi.close', 'module' => 'rfi', 'action' => 'close', 'description' => 'Close an RFI thread'],
        ['code' => 'rfi.escalate', 'module' => 'rfi', 'action' => 'escalate', 'description' => 'Escalate an RFI to another user'],

        // Submittals
        ['code' => 'submittal.view', 'module' => 'submittal', 'action' => 'view', 'description' => 'View submittals'],
        ['code' => 'submittal.create', 'module' => 'submittal', 'action' => 'create', 'description' => 'Create a submittal package'],
        ['code' => 'submittal.edit', 'module' => 'submittal', 'action' => 'edit', 'description' => 'Edit submittal metadata'],
        ['code' => 'submittal.delete', 'module' => 'submittal', 'action' => 'delete', 'description' => 'Delete a submittal'],
        ['code' => 'submittal.submit', 'module' => 'submittal', 'action' => 'submit', 'description' => 'Submit a submittal for review'],
        ['code' => 'submittal.review', 'module' => 'submittal', 'action' => 'review', 'description' => 'Review a submittal'],
        ['code' => 'submittal.approve', 'module' => 'submittal', 'action' => 'approve', 'description' => 'Approve a submittal'],
        ['code' => 'submittal.reject', 'module' => 'submittal', 'action' => 'reject', 'description' => 'Reject a submittal'],

        // Inspections
        ['code' => 'inspection.view', 'module' => 'inspection', 'action' => 'view', 'description' => 'View inspections'],
        ['code' => 'inspection.create', 'module' => 'inspection', 'action' => 'create', 'description' => 'Create an inspection record'],
        ['code' => 'inspection.edit', 'module' => 'inspection', 'action' => 'edit', 'description' => 'Edit an inspection'],
        ['code' => 'inspection.delete', 'module' => 'inspection', 'action' => 'delete', 'description' => 'Delete an inspection'],
        ['code' => 'inspection.schedule', 'module' => 'inspection', 'action' => 'schedule', 'description' => 'Schedule an inspection'],
        ['code' => 'inspection.conduct', 'module' => 'inspection', 'action' => 'conduct', 'description' => 'Log inspection activity'],
        ['code' => 'inspection.complete', 'module' => 'inspection', 'action' => 'complete', 'description' => 'Mark an inspection as complete'],

        // Auth-related actions
        ['code' => 'auth.logout', 'module' => 'auth', 'action' => 'logout', 'description' => 'Log out from the ZENA API'],
        ['code' => 'auth.me', 'module' => 'auth', 'action' => 'me', 'description' => 'Fetch authenticated user profile'],
        ['code' => 'auth.refresh', 'module' => 'auth', 'action' => 'refresh', 'description' => 'Refresh the sanctum token'],
        ['code' => 'auth.check-permission', 'module' => 'auth', 'action' => 'check-permission', 'description' => 'Verify permission ownership'],
        ['code' => 'auth.dashboard-url', 'module' => 'auth', 'action' => 'dashboard-url', 'description' => 'Get dashboard redirect URL'],
        ['code' => 'auth.notifications.view', 'module' => 'auth', 'action' => 'notifications.view', 'description' => 'View auth notifications'],
        ['code' => 'auth.notifications.read', 'module' => 'auth', 'action' => 'notifications.read', 'description' => 'Mark a notification as read'],
        ['code' => 'auth.test.simple', 'module' => 'auth', 'action' => 'test.simple', 'description' => 'Hit the simple auth test harness'],
        ['code' => 'auth.test.minimal', 'module' => 'auth', 'action' => 'test.minimal', 'description' => 'Authorize minimal auth test'],
        ['code' => 'auth.test.sanctum', 'module' => 'auth', 'action' => 'test.sanctum', 'description' => 'Authorize the sanctum auth test'],
        ['code' => 'auth.test.me', 'module' => 'auth', 'action' => 'test.me', 'description' => 'Authorize me-test diagnostics'],
        ['code' => 'auth.test.auth', 'module' => 'auth', 'action' => 'test.auth', 'description' => 'Authorize the generic auth test'],

        // PM dashboards
        ['code' => 'pm.dashboard', 'module' => 'pm', 'action' => 'dashboard', 'description' => 'Access PM dashboard overview'],
        ['code' => 'pm.progress', 'module' => 'pm', 'action' => 'progress', 'description' => 'View PM project progress'],
        ['code' => 'pm.risks', 'module' => 'pm', 'action' => 'risks', 'description' => 'Inspect PM risk assessments'],
        ['code' => 'pm.weekly-report', 'module' => 'pm', 'action' => 'weekly-report', 'description' => 'Generate PM weekly report'],

        // Designer dashboards
        ['code' => 'designer.dashboard', 'module' => 'designer', 'action' => 'dashboard', 'description' => 'View designer dashboard'],
        ['code' => 'designer.tasks', 'module' => 'designer', 'action' => 'tasks', 'description' => 'Inspect designer tasks'],
        ['code' => 'designer.drawings', 'module' => 'designer', 'action' => 'drawings', 'description' => 'Access designer drawing summary'],
        ['code' => 'designer.rfis', 'module' => 'designer', 'action' => 'rfis', 'description' => 'Inspect designer RFIs'],
        ['code' => 'designer.submittals', 'module' => 'designer', 'action' => 'submittals', 'description' => 'Inspect designer submittals'],
        ['code' => 'designer.workload', 'module' => 'designer', 'action' => 'workload', 'description' => 'Evaluate designer workload'],

        // Site engineer dashboards
        ['code' => 'site-engineer.dashboard', 'module' => 'site-engineer', 'action' => 'dashboard', 'description' => 'View site engineer dashboard'],
        ['code' => 'site-engineer.tasks', 'module' => 'site-engineer', 'action' => 'tasks', 'description' => 'View site engineer tasks'],
        ['code' => 'site-engineer.material-requests', 'module' => 'site-engineer', 'action' => 'material-requests', 'description' => 'Track material requests'],
        ['code' => 'site-engineer.rfis', 'module' => 'site-engineer', 'action' => 'rfis', 'description' => 'View site engineer RFIs'],
        ['code' => 'site-engineer.inspections', 'module' => 'site-engineer', 'action' => 'inspections', 'description' => 'Review site engineer inspections'],
        ['code' => 'site-engineer.safety', 'module' => 'site-engineer', 'action' => 'safety', 'description' => 'Check site safety status'],
        ['code' => 'site-engineer.daily-report', 'module' => 'site-engineer', 'action' => 'daily-report', 'description' => 'Retrieve daily site report'],

        // Project management
        ['code' => 'project.view', 'module' => 'project', 'action' => 'view', 'description' => 'View project records'],
        ['code' => 'project.create', 'module' => 'project', 'action' => 'create', 'description' => 'Create new projects'],
        ['code' => 'project.update', 'module' => 'project', 'action' => 'update', 'description' => 'Update existing projects'],
        ['code' => 'project.delete', 'module' => 'project', 'action' => 'delete', 'description' => 'Delete projects'],

        // Contract management
        ['code' => 'contract.view', 'module' => 'contract', 'action' => 'view', 'description' => 'View contracts'],
        ['code' => 'contract.create', 'module' => 'contract', 'action' => 'create', 'description' => 'Create contracts'],
        ['code' => 'contract.update', 'module' => 'contract', 'action' => 'update', 'description' => 'Update contracts'],
        ['code' => 'contract.delete', 'module' => 'contract', 'action' => 'delete', 'description' => 'Delete contracts'],
        ['code' => 'contract.payment.view', 'module' => 'contract', 'action' => 'payment.view', 'description' => 'View contract payments'],
        ['code' => 'contract.payment.create', 'module' => 'contract', 'action' => 'payment.create', 'description' => 'Create contract payments'],
        ['code' => 'contract.payment.update', 'module' => 'contract', 'action' => 'payment.update', 'description' => 'Update contract payments'],
        ['code' => 'contract.payment.delete', 'module' => 'contract', 'action' => 'payment.delete', 'description' => 'Delete contract payments'],

        // Task management
        ['code' => 'task.view', 'module' => 'task', 'action' => 'view', 'description' => 'View tasks'],
        ['code' => 'task.create', 'module' => 'task', 'action' => 'create', 'description' => 'Create tasks'],
        ['code' => 'task.update', 'module' => 'task', 'action' => 'update', 'description' => 'Update tasks'],
        ['code' => 'task.delete', 'module' => 'task', 'action' => 'delete', 'description' => 'Delete tasks'],
        ['code' => 'task.update-status', 'module' => 'task', 'action' => 'update-status', 'description' => 'Change task status'],
        ['code' => 'task.dependencies.view', 'module' => 'task', 'action' => 'dependencies.view', 'description' => 'View task dependencies'],
        ['code' => 'task.dependencies.add', 'module' => 'task', 'action' => 'dependencies.add', 'description' => 'Add task dependencies'],
        ['code' => 'task.dependencies.remove', 'module' => 'task', 'action' => 'dependencies.remove', 'description' => 'Remove task dependencies'],

        // Document management
        ['code' => 'document.view', 'module' => 'document', 'action' => 'view', 'description' => 'View documents'],
        ['code' => 'document.create', 'module' => 'document', 'action' => 'create', 'description' => 'Upload documents'],
        ['code' => 'document.update', 'module' => 'document', 'action' => 'update', 'description' => 'Update document metadata'],
        ['code' => 'document.delete', 'module' => 'document', 'action' => 'delete', 'description' => 'Delete documents'],

        // Notification management
        ['code' => 'notification.view', 'module' => 'notification', 'action' => 'view', 'description' => 'View notifications'],
        ['code' => 'notification.create', 'module' => 'notification', 'action' => 'create', 'description' => 'Send notifications'],
        ['code' => 'notification.read', 'module' => 'notification', 'action' => 'read', 'description' => 'Mark notification as read'],
        ['code' => 'notification.mark-all-read', 'module' => 'notification', 'action' => 'mark-all-read', 'description' => 'Mark all notifications as read'],
        ['code' => 'notification.delete', 'module' => 'notification', 'action' => 'delete', 'description' => 'Delete notifications'],
        ['code' => 'notification.stats', 'module' => 'notification', 'action' => 'stats', 'description' => 'Read notification metrics'],

        // Application settings
        ['code' => 'settings.general.read', 'module' => 'settings', 'action' => 'general.read', 'description' => 'View general settings'],
        ['code' => 'settings.general.update', 'module' => 'settings', 'action' => 'general.update', 'description' => 'Update general settings'],
        ['code' => 'settings.security.read', 'module' => 'settings', 'action' => 'security.read', 'description' => 'View security settings'],
        ['code' => 'settings.security.update', 'module' => 'settings', 'action' => 'security.update', 'description' => 'Update security settings'],

        // Change request management
        ['code' => 'change-request.view', 'module' => 'change-request', 'action' => 'view', 'description' => 'View change requests'],
        ['code' => 'change-request.create', 'module' => 'change-request', 'action' => 'create', 'description' => 'Create change requests'],
        ['code' => 'change-request.update', 'module' => 'change-request', 'action' => 'update', 'description' => 'Update change requests'],
        ['code' => 'change-request.delete', 'module' => 'change-request', 'action' => 'delete', 'description' => 'Delete change requests'],
        ['code' => 'change-request.submit', 'module' => 'change-request', 'action' => 'submit', 'description' => 'Submit a change request'],
        ['code' => 'change-request.approve', 'module' => 'change-request', 'action' => 'approve', 'description' => 'Approve a change request'],
        ['code' => 'change-request.reject', 'module' => 'change-request', 'action' => 'reject', 'description' => 'Reject a change request'],
        ['code' => 'change-request.apply', 'module' => 'change-request', 'action' => 'apply', 'description' => 'Apply a change request'],

        // Team management
        ['code' => 'team.view', 'module' => 'team', 'action' => 'view', 'description' => 'View teams'],
        ['code' => 'team.create', 'module' => 'team', 'action' => 'create', 'description' => 'Create teams'],
        ['code' => 'team.update', 'module' => 'team', 'action' => 'update', 'description' => 'Update teams'],
        ['code' => 'team.delete', 'module' => 'team', 'action' => 'delete', 'description' => 'Delete teams'],
        ['code' => 'team.archive', 'module' => 'team', 'action' => 'archive', 'description' => 'Archive teams'],
        ['code' => 'team.restore', 'module' => 'team', 'action' => 'restore', 'description' => 'Restore teams'],
        ['code' => 'team.member.view', 'module' => 'team', 'action' => 'member.view', 'description' => 'View team members'],
        ['code' => 'team.member.add', 'module' => 'team', 'action' => 'member.add', 'description' => 'Add team members'],
        ['code' => 'team.member.remove', 'module' => 'team', 'action' => 'member.remove', 'description' => 'Remove team members'],
        ['code' => 'team.member.update-role', 'module' => 'team', 'action' => 'member.update-role', 'description' => 'Update team member roles'],

        // Invitations
        ['code' => 'invitation.view', 'module' => 'invitation', 'action' => 'view', 'description' => 'View invitations'],
        ['code' => 'invitation.create', 'module' => 'invitation', 'action' => 'create', 'description' => 'Create invitations'],
        ['code' => 'invitation.revoke', 'module' => 'invitation', 'action' => 'revoke', 'description' => 'Revoke invitations'],
        ['code' => 'invitation.accept', 'module' => 'invitation', 'action' => 'accept', 'description' => 'Accept invitations'],
    ];

    private const PERMISSION_TABLE = 'permissions';

    public function run(): void
    {
        $table = self::PERMISSION_TABLE;
        $hasCodeColumn = Schema::hasColumn($table, 'code');
        $hasNameColumn = Schema::hasColumn($table, 'name');
        $lookupColumn = $hasCodeColumn ? 'code' : ($hasNameColumn ? 'name' : null);

        if ($lookupColumn === null) {
            $this->command?->warn('Skipping ZenaPermissionsSeeder: permissions table lacks code/name column.');
            return;
        }

        foreach (self::CANONICAL_PERMISSIONS as $permissionDefinition) {
            $permissionKey = $permissionDefinition['code'];

            $attributes = [
                'module' => $permissionDefinition['module'],
                'action' => $permissionDefinition['action'],
                'description' => $permissionDefinition['description'],
            ];

            if ($hasCodeColumn) {
                $attributes['code'] = $permissionKey;
            }

            if ($hasNameColumn) {
                $attributes['name'] = $permissionKey;
            }

            Permission::updateOrCreate([$lookupColumn => $permissionKey], $attributes);
        }
    }
}
