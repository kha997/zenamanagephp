<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
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
        ['code' => 'rfi.cancel', 'module' => 'rfi', 'action' => 'cancel', 'description' => 'Cancel an RFI'],

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
        ['code' => 'auth.permissions', 'module' => 'auth', 'action' => 'permissions', 'description' => 'Read authenticated permission matrix'],
        ['code' => 'auth.bulk.manage', 'module' => 'auth', 'action' => 'bulk.manage', 'description' => 'Run auth-scoped bulk import/export operations'],
        ['code' => 'auth.security.read', 'module' => 'auth', 'action' => 'security.read', 'description' => 'Read security dashboard data'],
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

        // Work templates
        ['code' => 'template.view', 'module' => 'template', 'action' => 'view', 'description' => 'View work templates'],
        ['code' => 'template.edit_draft', 'module' => 'template', 'action' => 'edit_draft', 'description' => 'Create or update template draft versions'],
        ['code' => 'template.publish', 'module' => 'template', 'action' => 'publish', 'description' => 'Publish immutable template versions'],
        ['code' => 'template.apply', 'module' => 'template', 'action' => 'apply', 'description' => 'Apply a published template version to a project'],
        ['code' => 'template.delete', 'module' => 'template', 'action' => 'delete', 'description' => 'Delete work templates'],

        // Document templates (thư viện biểu mẫu)
        ['code' => 'document_template.view', 'module' => 'document_template', 'action' => 'view', 'description' => 'View document templates'],
        ['code' => 'document_template.manage', 'module' => 'document_template', 'action' => 'manage', 'description' => 'Create, edit, publish document templates'],

        // Work instances
        ['code' => 'work.view', 'module' => 'work', 'action' => 'view', 'description' => 'View work instances'],
        ['code' => 'work.update', 'module' => 'work', 'action' => 'update', 'description' => 'Update work instance steps and values'],
        ['code' => 'work.approve', 'module' => 'work', 'action' => 'approve', 'description' => 'Approve or reject work steps'],
        ['code' => 'work.export', 'module' => 'work', 'action' => 'export', 'description' => 'Export work instance deliverables'],

        // Contract management
        ['code' => 'contract.view', 'module' => 'contract', 'action' => 'view', 'description' => 'View contracts'],
        ['code' => 'contract.create', 'module' => 'contract', 'action' => 'create', 'description' => 'Create contracts'],
        ['code' => 'contract.update', 'module' => 'contract', 'action' => 'update', 'description' => 'Update contracts'],
        ['code' => 'contract.delete', 'module' => 'contract', 'action' => 'delete', 'description' => 'Delete contracts'],
        ['code' => 'contract.payment.view', 'module' => 'contract', 'action' => 'payment.view', 'description' => 'View contract payments'],
        ['code' => 'contract.payment.create', 'module' => 'contract', 'action' => 'payment.create', 'description' => 'Create contract payments'],
        ['code' => 'contract.payment.update', 'module' => 'contract', 'action' => 'payment.update', 'description' => 'Update contract payments'],
        ['code' => 'contract.payment.delete', 'module' => 'contract', 'action' => 'payment.delete', 'description' => 'Delete contract payments'],
        ['code' => 'contract.expense.view', 'module' => 'contract', 'action' => 'expense.view', 'description' => 'View contract expenses'],
        ['code' => 'contract.expense.create', 'module' => 'contract', 'action' => 'expense.create', 'description' => 'Create contract expenses'],
        ['code' => 'contract.expense.delete', 'module' => 'contract', 'action' => 'expense.delete', 'description' => 'Delete contract expenses'],

        // Payment certificates (IPC)
        ['code' => 'payment_certificate.view', 'module' => 'payment_certificate', 'action' => 'view', 'description' => 'View payment certificates'],
        ['code' => 'payment_certificate.create', 'module' => 'payment_certificate', 'action' => 'create', 'description' => 'Create and edit payment certificates'],
        ['code' => 'payment_certificate.approve', 'module' => 'payment_certificate', 'action' => 'approve', 'description' => 'Approve payment certificates'],

        // Task management
        ['code' => 'task.view', 'module' => 'task', 'action' => 'view', 'description' => 'View tasks'],
        ['code' => 'task.create', 'module' => 'task', 'action' => 'create', 'description' => 'Create tasks'],
        ['code' => 'task.update', 'module' => 'task', 'action' => 'update', 'description' => 'Update tasks'],
        ['code' => 'task.delete', 'module' => 'task', 'action' => 'delete', 'description' => 'Delete tasks'],
        ['code' => 'task.update-status', 'module' => 'task', 'action' => 'update-status', 'description' => 'Change task status'],
        ['code' => 'task.dependencies.view', 'module' => 'task', 'action' => 'dependencies.view', 'description' => 'View task dependencies'],
        ['code' => 'task.dependencies.add', 'module' => 'task', 'action' => 'dependencies.add', 'description' => 'Add task dependencies'],
        ['code' => 'task.dependencies.remove', 'module' => 'task', 'action' => 'dependencies.remove', 'description' => 'Remove task dependencies'],
        ['code' => 'task.escalate-overdue', 'module' => 'task', 'action' => 'escalate-overdue', 'description' => 'Escalate overdue CAPA task'],

        // Document management
        ['code' => 'document.view', 'module' => 'document', 'action' => 'view', 'description' => 'View documents'],
        ['code' => 'document.create', 'module' => 'document', 'action' => 'create', 'description' => 'Upload documents'],
        ['code' => 'document.update', 'module' => 'document', 'action' => 'update', 'description' => 'Update document metadata'],
        ['code' => 'document.delete', 'module' => 'document', 'action' => 'delete', 'description' => 'Delete documents'],

        // Material catalog
        ['code' => 'material.view', 'module' => 'material', 'action' => 'view', 'description' => 'View canonical material catalog entries'],
        ['code' => 'material.create', 'module' => 'material', 'action' => 'create', 'description' => 'Create canonical material catalog entries'],
        ['code' => 'material.update', 'module' => 'material', 'action' => 'update', 'description' => 'Update canonical material catalog entries'],
        ['code' => 'material.delete', 'module' => 'material', 'action' => 'delete', 'description' => 'Delete canonical material catalog entries'],

        // Vendor master data
        ['code' => 'vendor.view', 'module' => 'vendor', 'action' => 'view', 'description' => 'View canonical vendor records'],
        ['code' => 'vendor.create', 'module' => 'vendor', 'action' => 'create', 'description' => 'Create canonical vendor records'],
        ['code' => 'vendor.update', 'module' => 'vendor', 'action' => 'update', 'description' => 'Update canonical vendor records'],
        ['code' => 'vendor.delete', 'module' => 'vendor', 'action' => 'delete', 'description' => 'Delete canonical vendor records'],

        // BOQ ownership
        ['code' => 'boq.view', 'module' => 'boq', 'action' => 'view', 'description' => 'View canonical BOQs and nested line items'],
        ['code' => 'boq.create', 'module' => 'boq', 'action' => 'create', 'description' => 'Create canonical BOQs and nested line items'],
        ['code' => 'boq.update', 'module' => 'boq', 'action' => 'update', 'description' => 'Update canonical BOQs and nested line items'],
        ['code' => 'boq.delete', 'module' => 'boq', 'action' => 'delete', 'description' => 'Delete canonical BOQs and nested line items'],

        // Material requests (procurement workflow)
        ['code' => 'material.read', 'module' => 'material', 'action' => 'read', 'description' => 'View material requests'],
        ['code' => 'material.request', 'module' => 'material', 'action' => 'request', 'description' => 'Create and update material requests'],
        ['code' => 'material.approve', 'module' => 'material', 'action' => 'approve', 'description' => 'Approve or reject material requests'],
        ['code' => 'material.receive', 'module' => 'material', 'action' => 'receive', 'description' => 'Fulfill/receive approved material requests'],

        // Material receipts (delivery + acceptance)
        ['code' => 'material-receipt.view', 'module' => 'material-receipt', 'action' => 'view', 'description' => 'View material delivery receipts'],
        ['code' => 'material-receipt.create', 'module' => 'material-receipt', 'action' => 'create', 'description' => 'Create and update material delivery receipts'],

        // Material receipt checklists (acceptance evidence)
        ['code' => 'material-receipt-checklist.view', 'module' => 'material-receipt-checklist', 'action' => 'view', 'description' => 'View acceptance checklists for receipts'],
        ['code' => 'material-receipt-checklist.create', 'module' => 'material-receipt-checklist', 'action' => 'create', 'description' => 'Create acceptance checklists for receipts'],

        // Material receipt lines (line items received)
        ['code' => 'material-receipt-line.view', 'module' => 'material-receipt-line', 'action' => 'view', 'description' => 'View material receipt line items'],
        ['code' => 'material-receipt-line.create', 'module' => 'material-receipt-line', 'action' => 'create', 'description' => 'Create and update material receipt line items'],

        // Site diaries (daily site logs)
        ['code' => 'site_diary.view', 'module' => 'site_diary', 'action' => 'view', 'description' => 'View daily site diaries'],
        ['code' => 'site_diary.create', 'module' => 'site_diary', 'action' => 'create', 'description' => 'Create, update and submit daily site diaries'],
        ['code' => 'site_diary.approve', 'module' => 'site_diary', 'action' => 'approve', 'description' => 'Approve submitted site diaries'],

        // Knowledge base (internal SOP/checklist/lessons-learned library)
        ['code' => 'knowledge.view', 'module' => 'knowledge', 'action' => 'view', 'description' => 'View published SOPs, checklists and lessons learned'],
        ['code' => 'knowledge.manage', 'module' => 'knowledge', 'action' => 'manage', 'description' => 'Create, edit, publish and delete knowledge base articles'],

        // CRM (lead inbox, accounts, opportunities — spec crm-zena)
        ['code' => 'crm.view', 'module' => 'crm', 'action' => 'view', 'description' => 'View CRM leads, accounts and opportunities'],
        ['code' => 'crm.manage', 'module' => 'crm', 'action' => 'manage', 'description' => 'Create/update leads, accounts, opportunities and move pipeline stages'],
        ['code' => 'crm.convert', 'module' => 'crm', 'action' => 'convert', 'description' => 'Convert won opportunities into projects'],
        ['code' => 'ai.suggest', 'module' => 'ai', 'action' => 'suggest', 'description' => 'Request AI-generated suggestions (e.g. lead-conversion service category and scope summary)'],

        // Design work management (design-item kanban — spec zena-ops-roadmap Phase 1)
        ['code' => 'design-item.view', 'module' => 'design-item', 'action' => 'view', 'description' => 'View design items and their review status'],
        ['code' => 'design-item.manage', 'module' => 'design-item', 'action' => 'manage', 'description' => 'Create/update design items, change review status, upload files'],

        // Reports
        ['code' => 'report.view', 'module' => 'report', 'action' => 'view', 'description' => 'View report export page'],
        ['code' => 'report.export', 'module' => 'report', 'action' => 'export', 'description' => 'Export tenant data to CSV reports'],

        // Webhooks (external integrations)
        ['code' => 'webhook.view', 'module' => 'webhook', 'action' => 'view', 'description' => 'View webhook endpoints'],
        ['code' => 'webhook.manage', 'module' => 'webhook', 'action' => 'manage', 'description' => 'Create, toggle and delete webhook endpoints'],

        // Notification management
        ['code' => 'notification.view', 'module' => 'notification', 'action' => 'view', 'description' => 'View notifications'],
        ['code' => 'notification.create', 'module' => 'notification', 'action' => 'create', 'description' => 'Send notifications'],
        ['code' => 'notification.read', 'module' => 'notification', 'action' => 'read', 'description' => 'Mark notification as read'],
        ['code' => 'notification.mark-all-read', 'module' => 'notification', 'action' => 'mark-all-read', 'description' => 'Mark all notifications as read'],
        ['code' => 'notification.delete', 'module' => 'notification', 'action' => 'delete', 'description' => 'Delete notifications'],
        ['code' => 'notification.stats', 'module' => 'notification', 'action' => 'stats', 'description' => 'Read notification metrics'],

        // Alert taxonomy (S6.2)
        ['code' => 'alert.view', 'module' => 'alert', 'action' => 'view', 'description' => 'View dashboard alerts'],
        ['code' => 'alert.read', 'module' => 'alert', 'action' => 'read', 'description' => 'Mark alert as read'],

        // Event record outbox (S6.3)
        ['code' => 'event-record.view', 'module' => 'event-record', 'action' => 'view', 'description' => 'Read event records for replayability'],

        // Application settings
        ['code' => 'settings.general.read', 'module' => 'settings', 'action' => 'general.read', 'description' => 'View general settings'],
        ['code' => 'settings.general.update', 'module' => 'settings', 'action' => 'general.update', 'description' => 'Update general settings'],
        ['code' => 'settings.security.read', 'module' => 'settings', 'action' => 'security.read', 'description' => 'View security settings'],
        ['code' => 'settings.security.update', 'module' => 'settings', 'action' => 'security.update', 'description' => 'Update security settings'],

        // User sidebar preferences
        ['code' => 'user-preferences.read', 'module' => 'user-preferences', 'action' => 'read', 'description' => 'View user sidebar preferences'],
        ['code' => 'user-preferences.update', 'module' => 'user-preferences', 'action' => 'update', 'description' => 'Update user sidebar preferences'],

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

        $this->grantTemplateApplyToProjectManager();
        $this->grantChangeRequestPermissionsToProjectManager();
    }

    /**
     * PM cần chọn & áp dụng WorkTemplate cho dự án — không cần quyền soạn thảo
     * (template.edit_draft/publish/delete vẫn admin-only qua ZenaAdminRolePermissionSeeder).
     */
    private function grantTemplateApplyToProjectManager(): void
    {
        $pmRole = Role::where('name', 'Project Manager')->first();

        if (!$pmRole) {
            return;
        }

        $permissionIds = Permission::whereIn('code', ['template.view', 'template.apply'])
            ->pluck('id')
            ->all();

        if (empty($permissionIds)) {
            return;
        }

        $pmRole->permissions()->syncWithoutDetaching($permissionIds);
    }

    /**
     * PM cần tạo/xem/duyệt/từ chối Change Request cho dự án mình quản lý --
     * mirrors the intent already expressed (but never actually enforced, due
     * to AUD-22's PermissionSeeder name-column bug) by the legacy
     * change_request.{create,read,approve,reject} grant in PermissionSeeder.
     *
     * Scope note: deliberately does NOT include change-request.submit or
     * change-request.apply -- PM can create/approve/reject but not move a CR
     * to submitted status themselves, nor apply an approved one. Confirm
     * with business whether this separation-of-duties is intentional before
     * widening (see AUD-22/AUD-23 in
     * docs/audits/2026-07-23-end-to-end-operational-audit.md).
     */
    private function grantChangeRequestPermissionsToProjectManager(): void
    {
        $pmRole = Role::where('name', 'Project Manager')->first();

        if (!$pmRole) {
            return;
        }

        $permissionIds = Permission::whereIn('code', [
            'change-request.view',
            'change-request.create',
            'change-request.approve',
            'change-request.reject',
        ])
            ->pluck('id')
            ->all();

        if (empty($permissionIds)) {
            return;
        }

        $pmRole->permissions()->syncWithoutDetaching($permissionIds);
    }
}
