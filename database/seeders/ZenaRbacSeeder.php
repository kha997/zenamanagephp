<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ZenaRole;
use App\Models\ZenaPermission;
use App\Models\Project;
use App\Models\Task;
use App\Models\Rfi;
use App\Models\Submittal;
use App\Models\ChangeRequest;
use App\Models\ZenaMaterialRequest;
use App\Models\ZenaPurchaseOrder;
use App\Models\ZenaInvoice;
use App\Models\ZenaQcPlan;
use App\Models\ZenaNcr;
use App\Models\ZenaDrawing;
use Illuminate\Support\Facades\Hash;

class ZenaRbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createPermissions();
        $this->createRoles();
        $this->createUsers();
        $this->assignRolePermissions();
        $this->createSampleData();
    }

    /**
     * Create permissions for Z.E.N.A system.
     */
    private function createPermissions(): void
    {
        $permissions = [
            // Project permissions
            ['name' => 'project.read', 'display_name' => 'View Projects', 'module' => 'projects'],
            ['name' => 'project.write', 'display_name' => 'Create/Edit Projects', 'module' => 'projects'],
            ['name' => 'project.assign', 'display_name' => 'Assign Projects', 'module' => 'projects'],
            ['name' => 'project.delete', 'display_name' => 'Delete Projects', 'module' => 'projects'],

            // Task permissions
            ['name' => 'task.read', 'display_name' => 'View Tasks', 'module' => 'tasks'],
            ['name' => 'task.write', 'display_name' => 'Create/Edit Tasks', 'module' => 'tasks'],
            ['name' => 'task.assign', 'display_name' => 'Assign Tasks', 'module' => 'tasks'],
            ['name' => 'task.delete', 'display_name' => 'Delete Tasks', 'module' => 'tasks'],

            // Drawing permissions
            ['name' => 'drawing.read', 'display_name' => 'View Drawings', 'module' => 'drawings'],
            ['name' => 'drawing.upload', 'display_name' => 'Upload Drawings', 'module' => 'drawings'],
            ['name' => 'drawing.review', 'display_name' => 'Review Drawings', 'module' => 'drawings'],
            ['name' => 'drawing.approve', 'display_name' => 'Approve Drawings', 'module' => 'drawings'],

            // RFI permissions
            ['name' => 'rfi.read', 'display_name' => 'View RFIs', 'module' => 'rfis'],
            ['name' => 'rfi.create', 'display_name' => 'Create RFIs', 'module' => 'rfis'],
            ['name' => 'rfi.answer', 'display_name' => 'Answer RFIs', 'module' => 'rfis'],
            ['name' => 'rfi.assign', 'display_name' => 'Assign RFIs', 'module' => 'rfis'],

            // Submittal permissions
            ['name' => 'submittal.read', 'display_name' => 'View Submittals', 'module' => 'submittals'],
            ['name' => 'submittal.create', 'display_name' => 'Create Submittals', 'module' => 'submittals'],
            ['name' => 'submittal.approve', 'display_name' => 'Approve Submittals', 'module' => 'submittals'],
            ['name' => 'submittal.review', 'display_name' => 'Review Submittals', 'module' => 'submittals'],

            // Change Request permissions
            ['name' => 'cr.read', 'display_name' => 'View Change Requests', 'module' => 'change_requests'],
            ['name' => 'cr.create', 'display_name' => 'Create Change Requests', 'module' => 'change_requests'],
            ['name' => 'cr.approve', 'display_name' => 'Approve Change Requests', 'module' => 'change_requests'],
            ['name' => 'cr.review', 'display_name' => 'Review Change Requests', 'module' => 'change_requests'],

            // QC permissions
            ['name' => 'qc.plan', 'display_name' => 'Plan QC Inspections', 'module' => 'qc'],
            ['name' => 'qc.inspect', 'display_name' => 'Perform QC Inspections', 'module' => 'qc'],
            ['name' => 'qc.approve', 'display_name' => 'Approve QC Results', 'module' => 'qc'],
            ['name' => 'qc.read', 'display_name' => 'View QC Data', 'module' => 'qc'],

            // NCR permissions
            ['name' => 'ncr.create', 'display_name' => 'Create NCRs', 'module' => 'ncrs'],
            ['name' => 'ncr.close', 'display_name' => 'Close NCRs', 'module' => 'ncrs'],
            ['name' => 'ncr.read', 'display_name' => 'View NCRs', 'module' => 'ncrs'],

            // Material Request permissions
            ['name' => 'material.request', 'display_name' => 'Request Materials', 'module' => 'materials'],
            ['name' => 'material.approve', 'display_name' => 'Approve Material Requests', 'module' => 'materials'],
            ['name' => 'material.receive', 'display_name' => 'Receive Materials', 'module' => 'materials'],
            ['name' => 'material.read', 'display_name' => 'View Material Requests', 'module' => 'materials'],

            // Purchase Order permissions
            ['name' => 'po.create', 'display_name' => 'Create Purchase Orders', 'module' => 'purchase_orders'],
            ['name' => 'po.approve', 'display_name' => 'Approve Purchase Orders', 'module' => 'purchase_orders'],
            ['name' => 'po.read', 'display_name' => 'View Purchase Orders', 'module' => 'purchase_orders'],

            // Invoice permissions
            ['name' => 'invoice.create', 'display_name' => 'Create Invoices', 'module' => 'invoices'],
            ['name' => 'invoice.approve', 'display_name' => 'Approve Invoices', 'module' => 'invoices'],
            ['name' => 'invoice.pay', 'display_name' => 'Process Payments', 'module' => 'invoices'],
            ['name' => 'invoice.read', 'display_name' => 'View Invoices', 'module' => 'invoices'],

            // Timesheet permissions
            ['name' => 'timesheet.submit', 'display_name' => 'Submit Timesheets', 'module' => 'timesheets'],
            ['name' => 'timesheet.approve', 'display_name' => 'Approve Timesheets', 'module' => 'timesheets'],
            ['name' => 'timesheet.read', 'display_name' => 'View Timesheets', 'module' => 'timesheets'],

            // Report permissions
            ['name' => 'report.view', 'display_name' => 'View Reports', 'module' => 'reports'],
            ['name' => 'report.export', 'display_name' => 'Export Reports', 'module' => 'reports'],

            // Admin permissions
            ['name' => 'admin.user.manage', 'display_name' => 'Manage Users', 'module' => 'admin'],
            ['name' => 'admin.role.manage', 'display_name' => 'Manage Roles', 'module' => 'admin'],
            ['name' => 'admin.system.manage', 'display_name' => 'Manage System', 'module' => 'admin'],
        ];

        foreach ($permissions as $permission) {
            ZenaPermission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }

    /**
     * Create roles for Z.E.N.A system.
     */
    private function createRoles(): void
    {
        $roles = [
            [
                'name' => 'SuperAdmin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'permissions' => ['*'], // All permissions
            ],
            [
                'name' => 'Admin',
                'display_name' => 'Administrator',
                'description' => 'System administration and user management',
                'permissions' => [
                    'project.read', 'project.write', 'project.assign',
                    'task.read', 'task.write', 'task.assign',
                    'drawing.read', 'drawing.upload', 'drawing.review', 'drawing.approve',
                    'rfi.read', 'rfi.create', 'rfi.answer', 'rfi.assign',
                    'submittal.read', 'submittal.create', 'submittal.approve', 'submittal.review',
                    'cr.read', 'cr.create', 'cr.approve', 'cr.review',
                    'qc.plan', 'qc.inspect', 'qc.approve', 'qc.read',
                    'ncr.create', 'ncr.close', 'ncr.read',
                    'material.request', 'material.approve', 'material.receive', 'material.read',
                    'po.create', 'po.approve', 'po.read',
                    'invoice.create', 'invoice.approve', 'invoice.pay', 'invoice.read',
                    'timesheet.submit', 'timesheet.approve', 'timesheet.read',
                    'report.view', 'report.export',
                    'admin.user.manage', 'admin.role.manage',
                ],
            ],
            [
                'name' => 'PM',
                'display_name' => 'Project Manager',
                'description' => 'Project management and oversight',
                'permissions' => [
                    'project.read', 'project.write', 'project.assign',
                    'task.read', 'task.write', 'task.assign',
                    'drawing.read', 'drawing.review',
                    'rfi.read', 'rfi.create', 'rfi.answer', 'rfi.assign',
                    'submittal.read', 'submittal.create', 'submittal.review',
                    'cr.read', 'cr.create', 'cr.review',
                    'qc.read',
                    'ncr.read',
                    'material.read',
                    'po.read',
                    'invoice.read',
                    'timesheet.read',
                    'report.view', 'report.export',
                ],
            ],
            [
                'name' => 'Designer',
                'display_name' => 'Designer',
                'description' => 'Design and drawing management',
                'permissions' => [
                    'project.read',
                    'task.read', 'task.write',
                    'drawing.read', 'drawing.upload', 'drawing.review',
                    'rfi.read', 'rfi.create', 'rfi.answer',
                    'submittal.read', 'submittal.create',
                    'cr.read', 'cr.create',
                    'timesheet.submit', 'timesheet.read',
                ],
            ],
            [
                'name' => 'SiteEngineer',
                'display_name' => 'Site Engineer',
                'description' => 'Site operations and field management',
                'permissions' => [
                    'project.read',
                    'task.read', 'task.write',
                    'drawing.read',
                    'rfi.read', 'rfi.create',
                    'submittal.read',
                    'cr.read', 'cr.create',
                    'qc.read',
                    'ncr.read',
                    'material.request', 'material.read',
                    'timesheet.submit', 'timesheet.read',
                ],
            ],
            [
                'name' => 'QC',
                'display_name' => 'Quality Control',
                'description' => 'Quality control and inspection',
                'permissions' => [
                    'project.read',
                    'task.read',
                    'drawing.read',
                    'rfi.read',
                    'submittal.read',
                    'cr.read',
                    'qc.plan', 'qc.inspect', 'qc.approve', 'qc.read',
                    'ncr.create', 'ncr.close', 'ncr.read',
                    'timesheet.submit', 'timesheet.read',
                ],
            ],
            [
                'name' => 'Procurement',
                'display_name' => 'Procurement',
                'description' => 'Procurement and vendor management',
                'permissions' => [
                    'project.read',
                    'task.read',
                    'drawing.read',
                    'rfi.read',
                    'submittal.read',
                    'cr.read',
                    'qc.read',
                    'ncr.read',
                    'material.request', 'material.approve', 'material.receive', 'material.read',
                    'po.create', 'po.approve', 'po.read',
                    'timesheet.submit', 'timesheet.read',
                ],
            ],
            [
                'name' => 'Finance',
                'display_name' => 'Finance',
                'description' => 'Financial management and accounting',
                'permissions' => [
                    'project.read',
                    'task.read',
                    'drawing.read',
                    'rfi.read',
                    'submittal.read',
                    'cr.read',
                    'qc.read',
                    'ncr.read',
                    'material.read',
                    'po.read',
                    'invoice.create', 'invoice.approve', 'invoice.pay', 'invoice.read',
                    'timesheet.read',
                    'report.view', 'report.export',
                ],
            ],
            [
                'name' => 'Client',
                'display_name' => 'Client',
                'description' => 'Client access with read-only permissions',
                'permissions' => [
                    'project.read',
                    'drawing.read',
                    'rfi.read',
                    'submittal.read',
                    'cr.read',
                    'report.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);
            
            $role = ZenaRole::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
            
            if ($permissions === ['*']) {
                // SuperAdmin gets all permissions
                $role->permissions()->sync(ZenaPermission::all()->pluck('id'));
            } else {
                $role->permissions()->sync(ZenaPermission::whereIn('name', $permissions)->pluck('id'));
            }
        }
    }

    /**
     * Create sample users for each role.
     */
    private function createUsers(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@zena.com',
                'password' => Hash::make('password123'),
                'phone' => '+1-555-0001',
                'role' => 'SuperAdmin',
            ],
            [
                'name' => 'System Admin',
                'email' => 'admin@zena.com',
                'password' => Hash::make('password123'),
                'phone' => '+1-555-0002',
                'role' => 'Admin',
            ],
            [
                'name' => 'John Project Manager',
                'email' => 'pm@zena.com',
                'password' => Hash::make('password123'),
                'phone' => '+1-555-0003',
                'role' => 'PM',
            ],
            [
                'name' => 'Sarah Designer',
                'email' => 'designer@zena.com',
                'password' => Hash::make('password123'),
                'phone' => '+1-555-0004',
                'role' => 'Designer',
            ],
            [
                'name' => 'Mike Site Engineer',
                'email' => 'site@zena.com',
                'password' => Hash::make('password123'),
                'phone' => '+1-555-0005',
                'role' => 'SiteEngineer',
            ],
            [
                'name' => 'Lisa QC Inspector',
                'email' => 'qc@zena.com',
                'password' => Hash::make('password123'),
                'phone' => '+1-555-0006',
                'role' => 'QC',
            ],
            [
                'name' => 'Tom Procurement',
                'email' => 'proc@zena.com',
                'password' => Hash::make('password123'),
                'phone' => '+1-555-0007',
                'role' => 'Procurement',
            ],
            [
                'name' => 'Jane Finance',
                'email' => 'finance@zena.com',
                'password' => Hash::make('password123'),
                'phone' => '+1-555-0008',
                'role' => 'Finance',
            ],
            [
                'name' => 'Client User',
                'email' => 'client@zena.com',
                'password' => Hash::make('password123'),
                'phone' => '+1-555-0009',
                'role' => 'Client',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);
            
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            $user->roles()->sync([ZenaRole::where('name', $role)->first()->id]);
        }
    }

    /**
     * Assign role permissions.
     */
    private function assignRolePermissions(): void
    {
        // This is already handled in createRoles method
        // But we can add additional role-permission assignments here if needed
    }

    /**
     * Create sample data for testing.
     */
    private function createSampleData(): void
    {
        $this->createSampleProjects();
        $this->createSampleTasks();
        $this->createSampleRfis();
        $this->createSampleSubmittals();
        $this->createSampleChangeRequests();
        $this->createSampleMaterialRequests();
        $this->createSamplePurchaseOrders();
        $this->createSampleInvoices();
        $this->createSampleQcPlans();
        $this->createSampleNcrs();
        $this->createSampleDrawings();
    }

    private function createSampleProjects(): void
    {
        $client = User::where('email', 'client@zena.com')->first();
        $pm = User::where('email', 'pm@zena.com')->first();

        $projects = [
            [
                'code' => 'P-2025-001',
                'name' => 'Office Building Construction',
                'description' => 'Construction of a 10-story office building in downtown',
                'client_id' => $client->id,
                'status' => 'active',
                'start_date' => now()->subMonths(3),
                'end_date' => now()->addMonths(9),
                'budget' => 5000000.00,
            ],
            [
                'code' => 'P-2025-002',
                'name' => 'Residential Complex',
                'description' => 'Construction of a residential complex with 50 units',
                'client_id' => $client->id,
                'status' => 'planning',
                'start_date' => now()->addMonth(),
                'end_date' => now()->addMonths(12),
                'budget' => 3000000.00,
            ],
        ];

        foreach ($projects as $projectData) {
            $project = Project::firstOrCreate(
                ['code' => $projectData['code']],
                $projectData
            );
            
            // Assign PM to project
            $project->users()->syncWithoutDetaching([$pm->id => ['role_on_project' => 'pm']]);
            
            // Assign client to project
            $project->users()->syncWithoutDetaching([$client->id => ['role_on_project' => 'client']]);
        }
    }

    private function createSampleTasks(): void
    {
        $project = Project::first();
        $pm = User::where('email', 'pm@zena.com')->first();
        $designer = User::where('email', 'designer@zena.com')->first();
        $siteEngineer = User::where('email', 'site@zena.com')->first();

        $tasks = [
            [
                'project_id' => $project->id,
                'title' => 'Foundation Design',
                'description' => 'Design foundation system for the building',
                'assignee_id' => $designer->id,
                'status' => 'completed',
                'priority' => 'high',
                'start_date' => now()->subMonths(2),
                'due_date' => now()->subMonth(),
                'progress' => 100,
            ],
            [
                'project_id' => $project->id,
                'title' => 'Site Preparation',
                'description' => 'Prepare construction site',
                'assignee_id' => $siteEngineer->id,
                'status' => 'in_progress',
                'priority' => 'high',
                'start_date' => now()->subMonth(),
                'due_date' => now()->addWeek(),
                'progress' => 75,
            ],
            [
                'project_id' => $project->id,
                'title' => 'Structural Design',
                'description' => 'Complete structural design drawings',
                'assignee_id' => $designer->id,
                'status' => 'pending',
                'priority' => 'medium',
                'start_date' => now()->addWeek(),
                'due_date' => now()->addMonth(),
                'progress' => 0,
            ],
        ];

        foreach ($tasks as $taskData) {
            Task::create($taskData);
        }
    }

    private function createSampleRfis(): void
    {
        $project = Project::first();
        $siteEngineer = User::where('email', 'site@zena.com')->first();
        $designer = User::where('email', 'designer@zena.com')->first();

        $rfis = [
            [
                'project_id' => $project->id,
                'subject' => 'Foundation Reinforcement',
                'question' => 'What is the required reinforcement for the foundation?',
                'asked_by' => $siteEngineer->id,
                'assigned_to' => $designer->id,
                'due_date' => now()->addDays(3),
                'status' => 'open',
            ],
            [
                'project_id' => $project->id,
                'subject' => 'Material Specifications',
                'question' => 'Can we use alternative materials for the facade?',
                'asked_by' => $siteEngineer->id,
                'assigned_to' => $designer->id,
                'due_date' => now()->addDays(5),
                'status' => 'answered',
                'answer' => 'Yes, but we need to check with the client first.',
                'answered_by' => $designer->id,
                'answered_at' => now()->subDay(),
            ],
        ];

        foreach ($rfis as $rfiData) {
            Rfi::create($rfiData);
        }
    }

    private function createSampleSubmittals(): void
    {
        $project = Project::first();
        $designer = User::where('email', 'designer@zena.com')->first();
        $pm = User::where('email', 'pm@zena.com')->first();

        $submittals = [
            [
                'project_id' => $project->id,
                'package_no' => 'SUB-001',
                'title' => 'Foundation Drawings',
                'description' => 'Foundation design drawings for approval',
                'status' => 'submitted',
                'due_date' => now()->addDays(7),
                'submitted_by' => $designer->id,
            ],
            [
                'project_id' => $project->id,
                'package_no' => 'SUB-002',
                'title' => 'Structural Drawings',
                'description' => 'Structural design drawings for approval',
                'status' => 'under_review',
                'due_date' => now()->addDays(10),
                'submitted_by' => $designer->id,
                'reviewed_by' => $pm->id,
                'reviewed_at' => now()->subDay(),
                'review_comments' => 'Please revise the beam sizes.',
            ],
        ];

        foreach ($submittals as $submittalData) {
            Submittal::create($submittalData);
        }
    }

    private function createSampleChangeRequests(): void
    {
        $project = Project::first();
        $siteEngineer = User::where('email', 'site@zena.com')->first();
        $pm = User::where('email', 'pm@zena.com')->first();

        $changeRequests = [
            [
                'project_id' => $project->id,
                'title' => 'Foundation Depth Change',
                'reason' => 'Soil conditions require deeper foundation',
                'impact_description' => 'Additional excavation and concrete work',
                'impact_cost' => 50000.00,
                'impact_time_days' => 5,
                'status' => 'submitted',
                'requested_by' => $siteEngineer->id,
            ],
            [
                'project_id' => $project->id,
                'title' => 'Material Substitution',
                'reason' => 'Original material not available',
                'impact_description' => 'Substitute with equivalent material',
                'impact_cost' => 10000.00,
                'impact_time_days' => 2,
                'status' => 'approved',
                'requested_by' => $siteEngineer->id,
                'reviewed_by' => $pm->id,
                'reviewed_at' => now()->subDay(),
                'review_comments' => 'Approved with conditions.',
            ],
        ];

        foreach ($changeRequests as $crData) {
            ChangeRequest::create($crData);
        }
    }

    private function createSampleMaterialRequests(): void
    {
        $project = Project::first();
        $siteEngineer = User::where('email', 'site@zena.com')->first();
        $procurement = User::where('email', 'proc@zena.com')->first();

        $materialRequests = [
            [
                'project_id' => $project->id,
                'request_number' => 'MR-001',
                'description' => 'Concrete for foundation',
                'status' => 'submitted',
                'estimated_cost' => 25000.00,
                'required_date' => now()->addDays(7),
                'requested_by' => $siteEngineer->id,
            ],
            [
                'project_id' => $project->id,
                'request_number' => 'MR-002',
                'description' => 'Steel reinforcement bars',
                'status' => 'approved',
                'estimated_cost' => 15000.00,
                'required_date' => now()->addDays(10),
                'requested_by' => $siteEngineer->id,
                'approved_by' => $procurement->id,
                'approved_at' => now()->subDay(),
            ],
        ];

        foreach ($materialRequests as $mrData) {
            ZenaMaterialRequest::create($mrData);
        }
    }

    private function createSamplePurchaseOrders(): void
    {
        $project = Project::first();
        $procurement = User::where('email', 'proc@zena.com')->first();
        $finance = User::where('email', 'finance@zena.com')->first();

        $purchaseOrders = [
            [
                'project_id' => $project->id,
                'po_number' => 'PO-001',
                'vendor_name' => 'ABC Concrete Supply',
                'description' => 'Concrete delivery for foundation',
                'status' => 'sent',
                'total_amount' => 25000.00,
                'due_date' => now()->addDays(7),
                'created_by' => $procurement->id,
            ],
            [
                'project_id' => $project->id,
                'po_number' => 'PO-002',
                'vendor_name' => 'Steel Works Inc',
                'description' => 'Steel reinforcement bars',
                'status' => 'approved',
                'total_amount' => 15000.00,
                'due_date' => now()->addDays(10),
                'created_by' => $procurement->id,
                'approved_by' => $finance->id,
                'approved_at' => now()->subDay(),
            ],
        ];

        foreach ($purchaseOrders as $poData) {
            ZenaPurchaseOrder::create($poData);
        }
    }

    private function createSampleInvoices(): void
    {
        $project = Project::first();
        $finance = User::where('email', 'finance@zena.com')->first();

        $invoices = [
            [
                'project_id' => $project->id,
                'invoice_number' => 'INV-001',
                'description' => 'Monthly progress payment',
                'amount' => 100000.00,
                'status' => 'sent',
                'due_date' => now()->addDays(30),
                'created_by' => $finance->id,
            ],
            [
                'project_id' => $project->id,
                'invoice_number' => 'INV-002',
                'description' => 'Material cost reimbursement',
                'amount' => 50000.00,
                'status' => 'paid',
                'due_date' => now()->subDays(5),
                'created_by' => $finance->id,
                'paid_at' => now()->subDay(),
            ],
        ];

        foreach ($invoices as $invoiceData) {
            ZenaInvoice::create($invoiceData);
        }
    }

    private function createSampleQcPlans(): void
    {
        $project = Project::first();
        $qc = User::where('email', 'qc@zena.com')->first();

        $qcPlans = [
            [
                'project_id' => $project->id,
                'title' => 'Foundation QC Plan',
                'description' => 'Quality control plan for foundation work',
                'status' => 'active',
                'planned_date' => now()->addDays(3),
                'created_by' => $qc->id,
            ],
            [
                'project_id' => $project->id,
                'title' => 'Structural QC Plan',
                'description' => 'Quality control plan for structural work',
                'status' => 'draft',
                'planned_date' => now()->addDays(10),
                'created_by' => $qc->id,
            ],
        ];

        foreach ($qcPlans as $qcPlanData) {
            ZenaQcPlan::create($qcPlanData);
        }
    }

    private function createSampleNcrs(): void
    {
        $project = Project::first();
        $qc = User::where('email', 'qc@zena.com')->first();
        $siteEngineer = User::where('email', 'site@zena.com')->first();

        $ncrs = [
            [
                'project_id' => $project->id,
                'ncr_number' => 'NCR-001',
                'title' => 'Foundation Cracking',
                'description' => 'Minor cracks found in foundation concrete',
                'status' => 'open',
                'severity' => 'medium',
                'created_by' => $qc->id,
                'assigned_to' => $siteEngineer->id,
            ],
            [
                'project_id' => $project->id,
                'ncr_number' => 'NCR-002',
                'title' => 'Material Quality Issue',
                'description' => 'Steel reinforcement does not meet specifications',
                'status' => 'closed',
                'severity' => 'high',
                'created_by' => $qc->id,
                'assigned_to' => $siteEngineer->id,
                'resolution' => 'Replaced with correct material',
                'resolved_at' => now()->subDay(),
            ],
        ];

        foreach ($ncrs as $ncrData) {
            ZenaNcr::create($ncrData);
        }
    }

    private function createSampleDrawings(): void
    {
        $project = Project::first();
        $designer = User::where('email', 'designer@zena.com')->first();

        $drawings = [
            [
                'project_id' => $project->id,
                'code' => 'DW-001',
                'name' => 'Foundation Plan',
                'version' => '1.0',
                'status' => 'approved',
                'file_name' => 'foundation_plan_v1.pdf',
                'file_size' => 2048000,
                'uploaded_by' => $designer->id,
            ],
            [
                'project_id' => $project->id,
                'code' => 'DW-002',
                'name' => 'Structural Plan',
                'version' => '1.0',
                'status' => 'review',
                'file_name' => 'structural_plan_v1.pdf',
                'file_size' => 3072000,
                'uploaded_by' => $designer->id,
            ],
        ];

        foreach ($drawings as $drawingData) {
            ZenaDrawing::create($drawingData);
        }
    }
}
