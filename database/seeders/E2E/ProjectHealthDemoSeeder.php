<?php declare(strict_types=1);

namespace Database\Seeders\E2E;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractExpense;
use Carbon\Carbon;

/**
 * Project Health Demo Seeder for E2E Tests
 * 
 * Round 82: Project Health vertical hardening + E2E flows
 * 
 * Creates a tenant with demo projects having different health statuses:
 * - P-GOOD-01: overall_status = good
 * - P-WARNING-01: overall_status = warning (schedule at_risk or cost at_risk)
 * - P-CRITICAL-01: overall_status = critical (delayed + over_budget)
 * 
 * Also creates:
 * - User with tenant.view_reports permission (pm role)
 * - User without tenant.view_reports permission (member role)
 */
class ProjectHealthDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting Project Health Demo Seeding for E2E...');

        // Create tenant for E2E health demo
        $tenantId = Str::ulid();
        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'E2E Health Demo Tenant',
            'domain' => 'e2e-health.local',
            'slug' => 'e2e-health-demo',
            'is_active' => true,
            'status' => 'active',
            'plan' => 'basic',
            'settings' => json_encode([
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'language' => 'vi'
            ]),
        ]);

        $this->command->info("✅ Created tenant: {$tenant->name} (ID: {$tenantId})");

        // Create client
        $client = Client::create([
            'tenant_id' => $tenantId,
            'name' => 'Demo Client Corp',
            'email' => 'client@demo.com',
            'company' => 'Demo Client Corporation',
        ]);

        // Create users
        // User WITH tenant.view_reports (pm role)
        $userWithReports = User::create([
            'tenant_id' => $tenantId,
            'name' => 'PM User',
            'email' => 'pm@e2e-health.local',
            'password' => Hash::make('password'),
            'role' => 'pm', // project_manager has view_reports
            'is_active' => true,
        ]);

        // User WITHOUT tenant.view_reports (member role)
        $userWithoutReports = User::create([
            'tenant_id' => $tenantId,
            'name' => 'Member User',
            'email' => 'member@e2e-health.local',
            'password' => Hash::make('password'),
            'role' => 'member', // member does NOT have view_reports
            'is_active' => true,
        ]);

        $this->command->info("✅ Created users: pm@e2e-health.local, member@e2e-health.local");

        // Create projects with different health statuses
        $today = Carbon::today();

        // P-GOOD-01: Good health
        // - All tasks on time
        // - Cost on budget
        $projectGood = Project::create([
            'tenant_id' => $tenantId,
            'code' => 'P-GOOD-01',
            'name' => 'Project Good Health',
            'description' => 'Project with good health status',
            'status' => 'active',
            'priority' => 'normal',
            'start_date' => $today->copy()->subMonths(2),
            'end_date' => $today->copy()->addMonths(4),
            'budget_total' => 1000000.00,
            'owner_id' => $userWithReports->id,
        ]);

        // Create tasks for P-GOOD-01 (all on time)
        Task::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectGood->id,
            'title' => 'Task 1 - Completed',
            'status' => 'done',
            'priority' => 'normal',
            'end_date' => $today->copy()->subDays(10),
            'assignee_id' => $userWithReports->id,
        ]);

        Task::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectGood->id,
            'title' => 'Task 2 - In Progress',
            'status' => 'in_progress',
            'priority' => 'normal',
            'end_date' => $today->copy()->addDays(5),
            'assignee_id' => $userWithReports->id,
        ]);

        Task::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectGood->id,
            'title' => 'Task 3 - Pending',
            'status' => 'pending',
            'priority' => 'normal',
            'end_date' => $today->copy()->addDays(10),
            'assignee_id' => $userWithReports->id,
        ]);

        // Create contract and expenses for P-GOOD-01 (on budget)
        $contractGood = Contract::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectGood->id,
            'client_id' => $client->id,
            'code' => 'CT-GOOD-01',
            'name' => 'Contract for Good Project',
            'status' => 'active',
            'total_value' => 1000000.00,
            'currency' => 'VND',
            'effective_from' => $today->copy()->subMonths(2),
            'effective_to' => $today->copy()->addMonths(4),
        ]);

        // Expenses within budget (90% of total_value)
        ContractExpense::create([
            'tenant_id' => $tenantId,
            'contract_id' => $contractGood->id,
            'name' => 'Expense 1',
            'amount' => 900000.00,
            'currency' => 'VND',
            'incurred_at' => $today->copy()->subDays(30),
            'status' => 'paid',
        ]);

        $this->command->info("✅ Created P-GOOD-01: {$projectGood->name}");

        // P-WARNING-01: Warning health
        // - Schedule at_risk (1-3 overdue tasks) OR cost at_risk
        $projectWarning = Project::create([
            'tenant_id' => $tenantId,
            'code' => 'P-WARNING-01',
            'name' => 'Project Warning Health',
            'description' => 'Project with warning health status',
            'status' => 'active',
            'priority' => 'normal',
            'start_date' => $today->copy()->subMonths(2),
            'end_date' => $today->copy()->addMonths(4),
            'budget_total' => 2000000.00,
            'owner_id' => $userWithReports->id,
        ]);

        // Create tasks for P-WARNING-01 (2 overdue tasks = at_risk)
        Task::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectWarning->id,
            'title' => 'Task 1 - Overdue',
            'status' => 'in_progress',
            'priority' => 'normal',
            'end_date' => $today->copy()->subDays(5),
            'assignee_id' => $userWithReports->id,
        ]);

        Task::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectWarning->id,
            'title' => 'Task 2 - Overdue',
            'status' => 'pending',
            'priority' => 'normal',
            'end_date' => $today->copy()->subDays(3),
            'assignee_id' => $userWithReports->id,
        ]);

        Task::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectWarning->id,
            'title' => 'Task 3 - On Time',
            'status' => 'pending',
            'priority' => 'normal',
            'end_date' => $today->copy()->addDays(10),
            'assignee_id' => $userWithReports->id,
        ]);

        Task::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectWarning->id,
            'title' => 'Task 4 - Completed',
            'status' => 'done',
            'priority' => 'normal',
            'end_date' => $today->copy()->subDays(20),
            'assignee_id' => $userWithReports->id,
        ]);

        // Create contract and expenses for P-WARNING-01 (on budget)
        $contractWarning = Contract::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectWarning->id,
            'client_id' => $client->id,
            'code' => 'CT-WARNING-01',
            'name' => 'Contract for Warning Project',
            'status' => 'active',
            'total_value' => 2000000.00,
            'currency' => 'VND',
            'effective_from' => $today->copy()->subMonths(2),
            'effective_to' => $today->copy()->addMonths(4),
        ]);

        ContractExpense::create([
            'tenant_id' => $tenantId,
            'contract_id' => $contractWarning->id,
            'name' => 'Expense 1',
            'amount' => 1800000.00,
            'currency' => 'VND',
            'incurred_at' => $today->copy()->subDays(30),
            'status' => 'paid',
        ]);

        $this->command->info("✅ Created P-WARNING-01: {$projectWarning->name}");

        // P-CRITICAL-01: Critical health
        // - Delayed (4+ overdue tasks) AND over_budget
        $projectCritical = Project::create([
            'tenant_id' => $tenantId,
            'code' => 'P-CRITICAL-01',
            'name' => 'Project Critical Health',
            'description' => 'Project with critical health status',
            'status' => 'active',
            'priority' => 'high',
            'start_date' => $today->copy()->subMonths(3),
            'end_date' => $today->copy()->addMonths(3),
            'budget_total' => 3000000.00,
            'owner_id' => $userWithReports->id,
        ]);

        // Create tasks for P-CRITICAL-01 (5 overdue tasks = delayed)
        for ($i = 1; $i <= 5; $i++) {
            Task::create([
                'tenant_id' => $tenantId,
                'project_id' => $projectCritical->id,
                'title' => "Task {$i} - Overdue",
                'status' => $i <= 2 ? 'in_progress' : 'pending',
                'priority' => 'high',
                'end_date' => $today->copy()->subDays(10 + $i),
                'assignee_id' => $userWithReports->id,
            ]);
        }

        Task::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectCritical->id,
            'title' => 'Task 6 - Completed',
            'status' => 'done',
            'priority' => 'normal',
            'end_date' => $today->copy()->subDays(30),
            'assignee_id' => $userWithReports->id,
        ]);

        // Create contract and expenses for P-CRITICAL-01 (over budget: 15% overrun)
        $contractCritical = Contract::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectCritical->id,
            'client_id' => $client->id,
            'code' => 'CT-CRITICAL-01',
            'name' => 'Contract for Critical Project',
            'status' => 'active',
            'total_value' => 3000000.00,
            'currency' => 'VND',
            'effective_from' => $today->copy()->subMonths(3),
            'effective_to' => $today->copy()->addMonths(3),
        ]);

        // Expenses over budget (115% of total_value = 15% overrun)
        ContractExpense::create([
            'tenant_id' => $tenantId,
            'contract_id' => $contractCritical->id,
            'name' => 'Expense 1 - Over Budget',
            'amount' => 3450000.00, // 115% of 3M
            'currency' => 'VND',
            'incurred_at' => $today->copy()->subDays(30),
            'status' => 'paid',
        ]);

        $this->command->info("✅ Created P-CRITICAL-01: {$projectCritical->name}");

        // Round 86: Add health snapshots for demo projects
        $this->command->info('📸 Creating health snapshots...');
        
        $snapshotService = app(\App\Services\Reports\ProjectHealthSnapshotService::class);
        
        // Create snapshot for P-GOOD-01 (today)
        try {
            $snapshotService->snapshotProjectHealthForProject($tenantId, $projectGood);
            $this->command->info("   • Created snapshot for P-GOOD-01");
        } catch (\Exception $e) {
            $this->command->warn("   • Skipped snapshot for P-GOOD-01: {$e->getMessage()}");
        }
        
        // Create snapshot for P-WARNING-01 (today)
        try {
            $snapshotService->snapshotProjectHealthForProject($tenantId, $projectWarning);
            $this->command->info("   • Created snapshot for P-WARNING-01");
        } catch (\Exception $e) {
            $this->command->warn("   • Skipped snapshot for P-WARNING-01: {$e->getMessage()}");
        }
        
        // Create snapshot for P-CRITICAL-01 (today)
        try {
            $snapshotService->snapshotProjectHealthForProject($tenantId, $projectCritical);
            $this->command->info("   • Created snapshot for P-CRITICAL-01");
        } catch (\Exception $e) {
            $this->command->warn("   • Skipped snapshot for P-CRITICAL-01: {$e->getMessage()}");
        }

        $this->command->info('🎉 Project Health Demo Seeding completed!');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info("   - Tenant: {$tenant->name} (ID: {$tenantId})");
        $this->command->info('   - Users:');
        $this->command->info("     • pm@e2e-health.local / password (has tenant.view_reports)");
        $this->command->info("     • member@e2e-health.local / password (no tenant.view_reports)");
        $this->command->info('   - Projects:');
        $this->command->info("     • P-GOOD-01: Good health");
        $this->command->info("     • P-WARNING-01: Warning health (schedule at_risk)");
        $this->command->info("     • P-CRITICAL-01: Critical health (delayed + over_budget)");
    }
}

